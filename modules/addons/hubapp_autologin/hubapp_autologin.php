<?php
/**
 * HubApp AutoLogin WHMCS - Configuração
 */

if (!defined("WHMCS")) die("Acesso negado");

function hubapp_autologin_config() {
    return [
        'name' => 'HubApp AutoLogin WHMCS',
        'description' => 'Login automático seguro para Área do Cliente (Home).',
        'version' => '1.0',
        'author' => 'HubApp',
        'fields' => [
            'autologin_key' => [
                'FriendlyName' => 'Chave Secreta',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Chave para assinar os tokens (JWT).',
            ],
            'expiration_hours' => [
                'FriendlyName' => 'Validade (Horas)',
                'Type' => 'text',
                'Size' => '3',
                'Default' => '72',
            ],
        ]
    ];
}

function hubapp_autologin_output($vars) {
    echo '<div style="padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <h3><i class="fas fa-check-circle"></i> Módulo Ativo</h3>
            <p>Utilize a variável abaixo em seus templates de e-mail:</p>
            <div style="background: #f8f9fa; padding: 10px; border-left: 4px solid #28a745;">
                <code>&lt;a href="{$autologin_url}"&gt;Acessar Minha Conta&lt;/a&gt;</code>
            </div>
          </div>';
}