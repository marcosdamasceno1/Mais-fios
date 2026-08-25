<?php
/**
 * ACLIVE — backend do formulário de contato
 *
 * Recebe os dados do formulário da landing page, envia por e-mail
 * e (opcional) encaminha o lead para um webhook de CRM.
 * Requer hospedagem com PHP (Hostinger, HostGator, Locaweb etc.).
 */

// ============================================================
// CONFIGURAÇÃO
// ============================================================

// E-mail que recebe os leads
$destinatario = 'digitalaclive@gmail.com';

// --- Integração com CRM via webhook (opcional) ---
// Cole a URL do webhook do seu CRM (Zapier, Make, RD, etc.).
// Deixe em branco ('') para desligar o envio ao CRM.
$CRM_WEBHOOK_URL = 'https://api.apiintegracoes.com/functions/v1/lead-form-webhook-ingest?token=7655040b-9ae1-4271-99c7-f9ab388444d0';
// Token do webhook, se o seu CRM exigir. Se o token já estiver
// dentro da URL, pode deixar isto em branco.
$CRM_WEBHOOK_TOKEN = '7655040b-9ae1-4271-99c7-f9ab388444d0';

// ============================================================

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido']);
    exit;
}

// Honeypot anti-spam: o campo "site" é invisível — se veio preenchido, é robô.
if (!empty($_POST['site'])) {
    echo json_encode(['ok' => true]); // finge sucesso para não avisar o robô
    exit;
}

$nome     = trim(strip_tags($_POST['nome'] ?? ''));
$telefone = trim(strip_tags($_POST['telefone'] ?? ''));
$email    = trim($_POST['email'] ?? '');
$mensagem = trim(strip_tags($_POST['mensagem'] ?? ''));
$origem   = trim(strip_tags($_POST['origem'] ?? 'Site'));

if ($nome === '' || $telefone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Preencha nome, WhatsApp e um e-mail válido.']);
    exit;
}

// Limita tamanhos para evitar abuso
$nome     = mb_substr($nome, 0, 120);
$telefone = mb_substr($telefone, 0, 30);
$mensagem = mb_substr($mensagem, 0, 2000);
$origem   = mb_substr($origem, 0, 60);

// ------------------------------------------------------------
// 1) Envia o lead para o CRM (webhook), se configurado.
//    É "à prova de falhas": se o CRM cair, o e-mail ainda vai.
// ------------------------------------------------------------
$crm_ok = enviar_para_crm($CRM_WEBHOOK_URL, $CRM_WEBHOOK_TOKEN, [
    'nome'       => $nome,
    'telefone'   => $telefone,
    'email'      => $email,
    'mensagem'   => $mensagem,
    'origem'     => $origem,
    'token'      => $CRM_WEBHOOK_TOKEN, // caso o webhook espere o token no corpo
    'enviado_em' => date('c'),
]);

// ------------------------------------------------------------
// 2) Envia o lead por e-mail.
// ------------------------------------------------------------
$assunto = "Novo lead do site Aclive ({$origem}): {$nome}";

$corpo = "Novo contato recebido pela landing page:\n\n"
       . "Origem: {$origem}\n"
       . "Nome: {$nome}\n"
       . "WhatsApp: {$telefone}\n"
       . "E-mail: {$email}\n\n"
       . "Mensagem:\n{$mensagem}\n\n"
       . 'Enviado em: ' . date('d/m/Y H:i:s');

// Remetente fixo do próprio domínio evita cair em spam;
// o Reply-To permite responder direto para o lead.
$headers = "From: Site Aclive <no-reply@digitalaclive.com.br>\r\n"
         . "Reply-To: {$nome} <{$email}>\r\n"
         . "Content-Type: text/plain; charset=utf-8\r\n";

$enviado = mail($destinatario, $assunto, $corpo, $headers);

// Sucesso se o e-mail OU o CRM receberam o lead.
if ($enviado || $crm_ok) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Falha ao enviar o lead.']);
}

/**
 * Envia o lead para o webhook do CRM. Retorna true se o webhook
 * respondeu com sucesso (HTTP 2xx). Nunca interrompe o fluxo.
 */
function enviar_para_crm($url, $token, array $payload) {
    if ($url === '') {
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    // Preferimos cURL; se não existir, usamos stream (file_get_contents).
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $json,
        'timeout'       => 6,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res !== false;
}
