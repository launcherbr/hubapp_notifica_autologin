<?php
namespace HubAppModule;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) die("Access Denied");

class HubAppClient {
    
    public static function getValidNumber($clientId) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->pluck('value', 'setting');
        $fieldId = (int)$config['whatsapp_field_id'];
        $num = ($fieldId > 0) ? Capsule::table('tblcustomfieldsvalues')->where('fieldid', $fieldId)->where('relid', $clientId)->value('value') : '';
        if (empty(trim($num))) $num = Capsule::table('tblclients')->where('id', $clientId)->value('phonenumber');
        
        $cleanNumber = preg_replace('/\D/', '', $num);
        if (strlen($cleanNumber) >= 10 && strlen($cleanNumber) <= 11) {
            if (substr($cleanNumber, 0, 1) === '0') $cleanNumber = substr($cleanNumber, 1);
            if (substr($cleanNumber, 0, 2) !== '55') $cleanNumber = '55' . $cleanNumber;
        }
        return $cleanNumber;
    }

    public static function send($clientId, $message, $externalKey) {
        $num = self::getValidNumber($clientId);
        return self::execute($num, $message, $externalKey);
    }

    public static function sendToAdmin($message, $externalKey) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->pluck('value', 'setting');
        $cleanAdmin = preg_replace('/\D/', '', $config['admin_whatsapp']);
        return self::execute($cleanAdmin, $message, $externalKey);
    }

    private static function execute($num, $msg, $key) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->pluck('value', 'setting');
        $gateway = $config['gateway_type'];
        $token = trim(str_replace('Bearer ', '', $config['api_token']));
        
        // Verifica se a opção de fechar ticket está marcada ('on' no WHMCS = true)
        $shouldCloseTicket = ($config['close_ticket'] === 'on');

        // Payload Base
        $payload = ["number" => $num];
        $headers = ['Content-Type: application/json'];

        if ($gateway == 'zpro') {
            // Z-Pro: Exige body, externalKey e suporta isClosed
            $payload["body"] = $msg;
            $payload["externalKey"] = (string)$key;
            
            // Aqui recuperamos a funcionalidade perdida:
            if ($shouldCloseTicket) {
                $payload["isClosed"] = true;
            }

            $headers[] = 'Authorization: Bearer ' . $token;
        } 
        elseif ($gateway == 'evolution') {
            // Evolution
            $payload["text"] = $msg;
            $payload["externalKey"] = (string)$key;
            // Algumas integrações de Evo também aceitam isClosed ou closeTicket nas options
            if ($shouldCloseTicket) {
               $payload["isClosed"] = true; 
            }
            $headers[] = 'apikey: ' . $token;
        } 
        else {
            // Whaticket (Padrão Minimalista)
            $payload["body"] = $msg;
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($config['api_endpoint']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $res = curl_exec($ch);
        curl_close($ch);
        
        return $res;
    }
}