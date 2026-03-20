<?php
/**
 * HubApp AutoLogin WHMCS - Hook Variável Única
 */

use WHMCS\Database\Capsule;

add_hook('EmailPreSend', 1, function($vars) {
    
    // Busca chave e configuração
    $config = Capsule::table('tbladdonmodules')
        ->where('module', 'hubapp_autologin')
        ->pluck('value', 'setting');
        
    $secretKey = $config['autologin_key'] ?? '';
    
    // CORREÇÃO: Busca a ID do Cliente de forma segura, sem usar o $relid (que traz a ID da fatura)
    $clientId = 0;
    if (!empty($vars['client_id'])) {
        $clientId = $vars['client_id'];
    } elseif (!empty($vars['clientsdetails']['userid'])) {
        $clientId = $vars['clientsdetails']['userid'];
    } elseif (!empty($vars['userid'])) {
        $clientId = $vars['userid'];
    }
    
    // Só gera se tiver chave e um ID de cliente válido
    if ($secretKey && $clientId > 0) {
        
        $systemUrl = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value'), '/');
        $expHours = (int)($config['expiration_hours'] ?? 72);
        
        // Cria o Token com a ID correta do Cliente
        $header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])));
        $payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode([
            'uid' => (int)$clientId,
            'exp' => time() + ($expHours * 3600),
            'iss' => 'HubApp'
        ])));
        
        $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', "$header.$payload", $secretKey, true)));
        
        // Retorna a variável pronta
        return ['autologin_url' => "$systemUrl/autologin.php?token=$header.$payload.$signature"];
    }
});
