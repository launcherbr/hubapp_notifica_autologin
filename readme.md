# 📱 HubApp Suite: Notifica & AutoLogin WHMCS

**Versão Unificada: Notificações via WhatsApp + Login Automático (JWT)**

Esta suíte de módulos para **WHMCS 9.x** e **PHP 8.3** combina o poder de comunicação instantânea do WhatsApp com a praticidade do "One-Click Login". Automatize cobranças, suporte e provisionamento enviando links que logam o cliente automaticamente na área do cliente, sem necessidade de senha.

---

## ✨ Funcionalidades Principais

### 🔔 HubApp Notifica (Comunicação)
* **Multi-Gateway**: Suporte nativo para **Whaticket**, **Evolution API (v2/Baileys)** e **Z-Pro**.
* **Zero Aprovação**: Envie mensagens de texto livre sem burocracia de templates pré-aprovados.
* **Sanitização Inteligente**: Correção automática de números (DDI+DDD) para garantir a entrega.
* **Rastreabilidade**: Suporte a `externalKey` para confirmação de leitura e status.

### 🛡️ HubApp AutoLogin (Acesso)
* **Login Mágico**: Gera links seguros que autenticam o usuário ao clicar.
* **Segurança JWT**: Tokens assinados com HMAC-SHA256, garantindo integridade e anti-adulteração.
* **Integração Total**: A variável de autologin funciona tanto em **E-mails** quanto no **WhatsApp**.

---

## 📂 Estrutura de Instalação

Extraia os arquivos mantendo a estrutura de diretórios do WHMCS:

```text
/ (Raiz do WHMCS)
├── autologin.php                # Gateway de Login (Core)
├── includes/
│   └── hooks/
│       └── hubapp_autologin_vars.php  # Hook para E-mails
├── modules/
│   └── addons/
│       ├── hubapp_autologin/    # Configuração do AutoLogin
│       └── hubapp_notifica/     # Configuração do WhatsApp
```

## 🚀 Configuração Rápida

Acesse **Opções (System Settings) > Módulos Addon** e ative ambos os módulos.

### 1. Configurar o AutoLogin
1.  Localize o módulo **HubApp AutoLogin** e clique em **Configure**.
2.  **Chave Secreta**: Crie uma senha forte (ex: uma hash longa ou frase aleatória). Esta chave é usada para assinar os tokens de segurança.
3.  **Validade**: Defina quantas horas o link deve durar (Padrão: `72`).
4.  Salve as alterações.

### 2. Configurar o Notifica (WhatsApp)
1.  Localize o módulo **HubApp Notifica** e clique em **Configure**.
2.  **Gateway**: Selecione sua API de preferência (**Whaticket**, **Evolution** ou **Z-Pro**).
3.  **Endpoint/Token**: Insira a URL da API e sua credencial de acesso.
4.  **WhatsApp Admin**: Insira seu número para receber alertas administrativos.
5.  **Fechar Ticket**: (Opcional para Z-Pro) Marque para encerrar tickets automaticamente após o envio da notificação.
6.  Salve as alterações.

---

## ⚙️ Variáveis de Personalização

Você pode usar estas variáveis nos templates de E-mail e nas mensagens de WhatsApp (na configuração do módulo HubApp Notifica). O sistema converte tudo dinamicamente antes do envio.

| Variável | O que ela exibe | Exemplo Prático |
| :--- | :--- | :--- |
| **`{autologin_url}`** | **Link de Login Automático** | `https://seuwhmcs.com/autologin.php?token=...` |
| `{firstname}` | Primeiro nome do cliente | João |
| `{invoiceid}` | ID da Fatura | 1025 |
| `{total}` | Valor Total | 59.90 |
| `{duedate}` | Data de Vencimento | 15/02/2026 |
| `{invoice_url}` | Link da Fatura (Padrão) | link.com/viewinvoice... |
| `{ticketsubject}`| Assunto do Ticket | Erro no VPS |
| `{ticketno}` | ID do Ticket | #849232 |
| `{domain}` | Domínio ou Produto | meudominio.com |
| `{username}` | Usuário do Serviço | admin_joao |
| `{password}` | Senha do Serviço | 123456 |
| `{x}` | Dias restantes (Domínio) | 5 |
| `{expirydate}` | Data de Expiração | 20/02/2026 |

> **💡 Dica de Ouro**: Experimente substituir `{invoice_url}` por `{autologin_url}` nas suas mensagens de "Fatura Gerada" ou "Lembrete de Atraso". Isso permite que o cliente acesse o painel e pague a fatura instantaneamente, sem precisar lembrar a senha, aumentando sua taxa de conversão.

---

## 📋 Automações Disponíveis (Hooks)

O sistema monitora os seguintes eventos do WHMCS e dispara as mensagens configuradas:

### 💰 Financeiro
* **Fatura Gerada**: Envia o link e vencimento assim que a fatura é criada.
* **Pagamento Confirmado**: Agradecimento automático após a baixa.
* **Lembretes de Atraso**: Régua de cobrança completa (1º, 2º e 3º aviso antes da suspensão).

### 🛠️ Suporte & Admin
* **Resposta em Ticket**: Avisa o cliente quando o suporte responde (Admin -> Cliente).
* **Novo Ticket (Admin)**: Alerta o administrador no WhatsApp sobre novos chamados abertos.
* **Login Admin**: Segurança proativa avisando sobre qualquer acesso ao painel administrativo.

### 📦 Produtos & Serviços
* **Serviço Ativado**: Envia dados de acesso (Login/Senha) após o provisionamento.
* **Serviço Suspenso**: Notifica sobre suspensão automática por falta de pagamento.
* **Renovação de Domínio**: Avisa dias antes do domínio expirar.

---

## 🔒 Detalhes de Segurança (JWT)

O sistema de AutoLogin utiliza três camadas de proteção para garantir a segurança dos seus clientes:

1.  **Integridade**: A assinatura `HMAC-SHA256` garante que o token não foi alterado. Se um usuário tentar mudar o ID no link, o acesso é negado.
2.  **Expiração (TTL)**: O link expira automaticamente após o tempo configurado (ex: 72h). Mesmo que o link vaze após esse período, ele será inútil.
3.  **Vínculo Único**: O token contém o `UID` do cliente. Ele loga apenas na conta daquele usuário específico.

---

## 💎 Recomendado para seu WHMCS

> **TENHA SEU WHMCS VERIFICADO**
>
> Garanta mais credibilidade e segurança para o seu sistema por apenas **R$ 250,00 anuais**.
>
> [**👉 CLIQUE AQUI PARA CONTRATAR AGORA**](https://licencas.digital/store/whmcs/whmcs-validado)

---

## 🆘 Suporte e Créditos

* **Desenvolvido por**: LD | HubApp / Launcher & Co.
* **Suporte e Atualizações**: [licencas.digital](https://licencas.digital)