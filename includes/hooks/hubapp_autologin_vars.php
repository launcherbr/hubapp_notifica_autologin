<?php
/**
 * HubApp AutoLogin WHMCS - Hook Variável Única (Versão Inteligente)
 */

use WHMCS\Database\Capsule;

add_hook('EmailPreSend', 1, function($vars) {
    
    try {
        // 1. Busca a chave secreta
        $secretKey = Capsule::table('tbladdonmodules')
            ->where('module', 'hubapp_autologin')
            ->where('setting', 'autologin_key')
            ->value('value');
            
        $expHours = Capsule::table('tbladdonmodules')
            ->where('module', 'hubapp_autologin')
            ->where('setting', 'expiration_hours')
            ->value('value');
            
        if (!$expHours) $expHours = 72;
        
        // 2. BUSCA INTELIGENTE DA ID DO CLIENTE
        $clientId = 0;
        
        if (!empty($vars['client_id'])) {
            $clientId = $vars['client_id']; // Padrão
        } elseif (!empty($vars['clientsdetails']['userid'])) {
            $clientId = $vars['clientsdetails']['userid']; // E-mails gerais
        } elseif (!empty($vars['userid'])) {
            $clientId = $vars['userid']; // Fallback
        } elseif (!empty($vars['relid']) && !empty($vars['messagename'])) {
            // SEGREDO AQUI: Se for e-mail de Boas-Vindas ou Senha, o relid é o Cliente!
            $msgName = strtolower($vars['messagename']);
            if (strpos($msgName, 'client signup') !== false || strpos($msgName, 'welcome') !== false || strpos($msgName, 'password') !== false) {
                $clientId = $vars['relid'];
            }
        }
        
        // 3. Se tivermos Chave e ID de Cliente, gera o link
        if (!empty($secretKey) && $clientId > 0) {
            
            $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
            $systemUrl = rtrim($systemUrl, '/');
            
            // Cria o Token JWT
            $header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])));
            $payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode([
                'uid' => (int)$clientId,
                'exp' => time() + ((int)$expHours * 3600),
                'iss' => 'HubApp'
            ])));
            
            $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', "$header.$payload", $secretKey, true)));
            
            // Retorna a variável pronta para o e-mail
            return ['autologin_url' => "$systemUrl/autologin.php?token=$header.$payload.$signature"];
        }
        
    } catch (\Exception $e) {
        // Ignora erros para não travar envios
    }
});