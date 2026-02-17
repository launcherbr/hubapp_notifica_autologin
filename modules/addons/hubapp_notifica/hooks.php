<?php
/**
 * HubApp Notifica WHMCS (v1.6.0)
 * Integrado com AutoLogin JWT
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;
use HubAppModule\HubAppClient;

require_once __DIR__ . '/lib/HubAppClient.php';

/**
 * GERA O LINK DE AUTOLOGIN (JWT)
 * Busca a configuração do módulo HubApp AutoLogin e gera o token
 */
function hubapp_generate_autologin($userid) {
    try {
        // 1. Busca configurações do Módulo AutoLogin (e não do Notifica)
        $config = Capsule::table('tbladdonmodules')
            ->where('module', 'hubapp_autologin')
            ->pluck('value', 'setting');

        $secretKey = $config['autologin_key'] ?? '';
        
        // Se não tiver chave configurada ou usuário inválido, retorna apenas a URL do sistema
        $systemUrl = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value'), '/');
        
        if (!$secretKey || $userid <= 0) {
            return $systemUrl; // Fallback seguro
        }

        $expHours = (int)($config['expiration_hours'] ?? 72);

        // 2. Cria o Token (Mesma lógica do autologin.php)
        $header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])));
        
        $payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode([
            'uid' => (int)$userid,
            'exp' => time() + ($expHours * 3600),
            'iss' => 'HubApp'
        ])));

        $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', "$header.$payload", $secretKey, true)));

        return "$systemUrl/autologin.php?token=$header.$payload.$signature";

    } catch (\Exception $e) {
        // Em caso de erro no banco, retorna URL base para não quebrar o envio
        return ''; 
    }
}

/**
 * Função Auxiliar de Disparo
 * Realiza o replace de variáveis e envia a externalKey obrigatória.
 */
function hubapp_dispatch($hook, $uid, $replacements, $externalKey) {
    // Busca o template da mensagem
    $tpl = Capsule::table('tbladdonmodules')
        ->where('module', 'hubapp_notifica')
        ->where('setting', 'template_' . $hook)
        ->value('value');

    if (empty($tpl)) return;

    // --- INTEGRAÇÃO AUTOLOGIN ---
    // Gera o link e adiciona ao array de substituições
    $replacements['{autologin_url}'] = hubapp_generate_autologin($uid);
    // ----------------------------

    $message = str_replace(array_keys($replacements), array_values($replacements), $tpl);
    return HubAppClient::send($uid, $message, $externalKey);
}

// --- GATILHOS DE FATURAMENTO ---

add_hook('InvoiceCreationPreEmail', 1, function($vars) {
    $invoiceId = $vars['invoiceid'];
    $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    
    hubapp_dispatch('InvoiceCreated', $cli->id, [
        '{firstname}' => $cli->firstname,
        '{invoiceid}' => $invoiceId,
        '{total}' => $inv->total,
        '{duedate}' => fromMySQLDate($inv->duedate),
        '{invoice_url}' => $systemUrl . "viewinvoice.php?id=" . $invoiceId 
        // Nota: {invoice_url} leva ao login normal, {autologin_url} leva direto logado
    ], "INV_NEW_" . $invoiceId);
});

add_hook('InvoicePaid', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    
    hubapp_dispatch('InvoicePaid', $cli->id, [
        '{firstname}' => $cli->firstname, 
        '{invoiceid}' => $vars['invoiceid']
    ], "INV_PAID_" . $vars['invoiceid']);
});

add_hook('InvoicePaymentReminder', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    $type = ucfirst($vars['type']); // First, Second, Third
    
    hubapp_dispatch('InvoicePaymentReminder' . $type, $cli->id, [
        '{firstname}' => $cli->firstname,
        '{invoiceid}' => $vars['invoiceid'],
        '{duedate}' => fromMySQLDate($inv->duedate)
    ], "INV_REM_" . strtoupper($type) . "_" . $vars['invoiceid']);
});

// --- GATILHOS DE SUPORTE ---

add_hook('TicketAdminReply', 1, function($vars) {
    $ticket = Capsule::table('tbltickets')->where('id', $vars['ticketid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $ticket->userid)->first();

    hubapp_dispatch('TicketAdminReply', $cli->id, [
        '{firstname}' => $cli->firstname,
        '{ticketsubject}' => $ticket->title,
        '{ticketno}' => $ticket->tid
    ], "TK_REP_" . $vars['ticketid']);
});

add_hook('TicketOpen', 1, function($vars) {
    // Este hook envia para ADMIN, então não gera link de autologin do cliente
    $tpl = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_TicketOpenAdmin')->value('value');
    if (!$tpl) return;

    $firstName = ($vars['userid']) ? Capsule::table('tblclients')->where('id', $vars['userid'])->value('firstname') : "Visitante";

    $msg = str_replace(['{subject}', '{firstname}', '{priority}'], [$vars['subject'], $firstName, $vars['priority']], $tpl);
    HubAppClient::sendToAdmin($msg, "ADM_TK_OPEN_" . $vars['ticketid']);
});

// --- GATILHOS DE SERVIÇOS E SEGURANÇA ---

add_hook('AfterModuleCreate', 1, function($vars) {
    $p = $vars['params'];
    $firstName = Capsule::table('tblclients')->where('id', $p['userid'])->value('firstname');
    
    hubapp_dispatch('AfterModuleCreate', $p['userid'], [
        '{firstname}' => $firstName, 
        '{domain}' => $p['domain'], 
        '{username}' => $p['username'], 
        '{password}' => $p['password']
    ], "SVC_ACT_" . $p['accountid']);
});

add_hook('AfterModuleSuspend', 1, function($vars) {
    $p = $vars['params'];
    $firstName = Capsule::table('tblclients')->where('id', $p['userid'])->value('firstname');
    
    hubapp_dispatch('AfterModuleSuspend', $p['userid'], [
        '{firstname}' => $firstName, 
        '{domain}' => $p['domain']
    ], "SVC_SUSP_" . $p['accountid']);
});

add_hook('AdminLogin', 1, function($vars) {
    $tpl = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_AdminLogin')->value('value');
    if ($tpl) {
        $msg = str_replace('{username}', $vars['username'], $tpl);
        HubAppClient::sendToAdmin($msg, "ADM_LGN_" . time());
    }
});

add_hook('DomainRenewalNotice', 1, function($vars) {
    $dom = Capsule::table('tbldomains')->where('id', $vars['domainid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $dom->userid)->first();

    hubapp_dispatch('DomainRenewalNotice', $cli->id, [
        '{firstname}' => $cli->firstname,
        '{domain}' => $dom->domain,
        '{x}' => $vars['daysuntilexpiry'],
        '{expirydate}' => fromMySQLDate($dom->expirydate)
    ], "DOM_EXP_" . $vars['domainid']);
});