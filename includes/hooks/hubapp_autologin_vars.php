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
    $userid = $vars['userid'] ?? ($vars['relid'] ?? 0);
    
    // Só gera se tiver chave e usuário válido
    if ($secretKey && $userid > 0) {
        
        $systemUrl = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value'), '/');
        $expHours = (int)($config['expiration_hours'] ?? 72);
        
        // Cria o Token
        $header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])));
        $payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode([
            'uid' => (int)$userid,
            'exp' => time() + ($expHours * 3600),
            'iss' => 'HubApp'
        ])));
        
        $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', "$header.$payload", $secretKey, true)));
        
        // Retorna a variável pronta
        return ['autologin_url' => "$systemUrl/autologin.php?token=$header.$payload.$signature"];
    }
});