<?php
/**
 * HubApp AutoLogin WHMCS - Gateway Clean
 * Foco: Login direto e rápido na Home
 */

ob_start(); // Previne erros de "headers already sent"

require_once __DIR__ . '/init.php';

use WHMCS\Database\Capsule;
use WHMCS\Session;

$token = $_GET['token'] ?? '';

try {
    // 1. Busca Configuração
    $secretKey = Capsule::table('tbladdonmodules')
        ->where('module', 'hubapp_autologin')
        ->where('setting', 'autologin_key')
        ->value('value');

    if (!$token || !$secretKey) {
        throw new \Exception("Acesso inválido.");
    }

    // 2. Valida Assinatura JWT
    $parts = explode('.', $token);
    if (count($parts) !== 3) throw new \Exception("Token inválido.");

    list($header, $payload, $signature) = $parts;
    $checkSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', "$header.$payload", $secretKey, true)));
    
    if (!hash_equals($checkSignature, $signature)) {
        throw new \Exception("Assinatura inválida.");
    }

    $data = json_decode(base64_decode($payload), true);
    if ($data['exp'] < time()) {
        throw new \Exception("Link expirado.");
    }

    $clientId = (int)$data['uid'];

    // 3. Autenticação Nativa WHMCS 9
    $userRelation = Capsule::table('tblusers_clients')->where('client_id', $clientId)->first();

    if ($userRelation && $user = \WHMCS\User\User::find($userRelation->auth_user_id)) {
        
        // Limpa sessão anterior se existir
        if (\Auth::user()) {
            \Auth::logout();
        }

        // Login Oficial
        \Auth::login($user);

        // Fixação de Sessão (Vital para WHMCS 9)
        Session::set("uid", $clientId);
        Session::set("user_id", $user->id);
        Session::set("upw", $user->password);
        
        // [IMPORTANTE] Força a escrita no disco antes do redirect
        // Isso é o que faz funcionar no Nginx/Cloudflare sem precisar do JS
        session_write_close();
        
        // Redirecionamento Direto (Sem tela de carregamento)
        header("Location: clientarea.php");
        exit;
    }
    
    throw new \Exception("Usuário não encontrado.");

} catch (\Exception $e) {
    // Em caso de erro, manda para o login normal
    header("Location: clientarea.php");
    exit;
}