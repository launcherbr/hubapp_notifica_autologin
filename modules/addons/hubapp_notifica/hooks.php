<?php
/**
 * HubApp Notifica WHMCS (v1.6.0)
 * Suporta: Whaticket, Evolution API e Z-Pro
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;
use HubAppModule\HubAppClient;

require_once __DIR__ . '/lib/HubAppClient.php';

/**
 * Função Auxiliar de Disparo
 * Realiza o replace de variáveis e envia a externalKey obrigatória.
 */
function hubapp_dispatch($hook, $uid, $replacements, $externalKey) {
    $tpl = Capsule::table('tbladdonmodules')
        ->where('module', 'hubapp_notifica')
        ->where('setting', 'template_' . $hook)
        ->value('value');

    if (empty($tpl)) return;

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

add_hook('InvoiceUnpaid', 1, function($vars) {
    $invoiceId = $vars['invoiceid'];
    $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    
    // Evita erro caso a fatura não seja encontrada
    if (!$inv) return;
    
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    
    hubapp_dispatch('InvoiceUnpaid', $cli->id, [
        '{firstname}' => $cli->firstname,
        '{invoiceid}' => $invoiceId,
        '{total}' => formatCurrency($inv->total), // Opcional, formata a moeda nativamente se precisar
        '{duedate}' => fromMySQLDate($inv->duedate),
        '{invoice_url}' => $systemUrl . "viewinvoice.php?id=" . $invoiceId
    ], "INV_UNPAID_" . $invoiceId);
});

add_hook('InvoicePaymentReminder', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    
    // Se for o aviso pré-vencimento (reminder), direciona para Fatura a Vencer (InvoiceUnpaid)
    if ($vars['type'] == 'reminder') {
        hubapp_dispatch('InvoiceUnpaid', $cli->id, [
            '{firstname}' => $cli->firstname,
            '{invoiceid}' => $vars['invoiceid'],
            '{total}' => $inv->total,
            '{duedate}' => fromMySQLDate($inv->duedate),
            '{invoice_url}' => $systemUrl . "viewinvoice.php?id=" . $vars['invoiceid']
        ], "INV_UNPAID_REM_" . $vars['invoiceid']);
    } else {
        // Trata os avisos de atraso: First, Second, Third
        $type = ucfirst($vars['type']); 
        hubapp_dispatch('InvoicePaymentReminder' . $type, $cli->id, [
            '{firstname}' => $cli->firstname,
            '{invoiceid}' => $vars['invoiceid'],
            '{duedate}' => fromMySQLDate($inv->duedate)
        ], "INV_REM_" . strtoupper($type) . "_" . $vars['invoiceid']);
    }
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