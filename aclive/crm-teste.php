<?php
/**
 * DIAGNÓSTICO do webhook do CRM — ARQUIVO TEMPORÁRIO.
 * Suba na raiz do public_html e abra no navegador:
 *   https://digitalaclive.com.br/crm-teste.php
 * Ele envia um lead de TESTE em 3 formatos e mostra a resposta do webhook.
 * APAGUE este arquivo depois de diagnosticar.
 */

header('Content-Type: text/plain; charset=utf-8');

$url   = 'https://api.apiintegracoes.com/functions/v1/lead-form-webhook-ingest?token=7655040b-9ae1-4271-99c7-f9ab388444d0';
$token = '7655040b-9ae1-4271-99c7-f9ab388444d0';

echo "=== DIAGNOSTICO WEBHOOK CRM ===\n";
echo "cURL disponivel: " . (function_exists('curl_init') ? 'SIM' : 'NAO') . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'SIM' : 'NAO') . "\n\n";

function enviar($url, $headers, $body, $rotulo) {
    echo "------------------------------------------------------------\n";
    echo ">> $rotulo\n";
    echo "Content-Type: " . implode(' | ', $headers) . "\n";
    echo "Corpo: $body\n\n";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        echo "HTTP: $code\n";
        if ($err !== '') echo "Erro cURL: $err\n";
        echo "Resposta: $resp\n\n";
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'timeout'       => 15,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        echo "HTTP (headers): " . (isset($http_response_header) ? implode(' ', array_slice($http_response_header, 0, 1)) : 'sem resposta') . "\n";
        echo "Resposta: " . var_export($resp, true) . "\n\n";
    }
}

$authHeader = 'Authorization: Bearer ' . $token;

// 1) JSON com nomes em portugues
enviar($url,
    ['Content-Type: application/json', $authHeader],
    json_encode(['nome'=>'TESTE Aclive','telefone'=>'31999999999','email'=>'teste@aclive.com','mensagem'=>'teste 1','origem'=>'Advocacia','token'=>$token]),
    'Formato 1: JSON (nome/telefone/email/mensagem)');

// 2) JSON com nomes em ingles
enviar($url,
    ['Content-Type: application/json', $authHeader],
    json_encode(['name'=>'TESTE Aclive','phone'=>'31999999999','email'=>'teste@aclive.com','message'=>'teste 2','source'=>'Advocacia','token'=>$token]),
    'Formato 2: JSON (name/phone/email/message)');

// 3) Formulario (x-www-form-urlencoded)
enviar($url,
    ['Content-Type: application/x-www-form-urlencoded', $authHeader],
    http_build_query(['nome'=>'TESTE Aclive','name'=>'TESTE Aclive','telefone'=>'31999999999','phone'=>'31999999999','email'=>'teste@aclive.com','mensagem'=>'teste 3','message'=>'teste 3','origem'=>'Advocacia','token'=>$token]),
    'Formato 3: form-urlencoded (pt + en juntos)');

echo "=== FIM. Copie tudo acima e envie para analise. Depois APAGUE este arquivo. ===\n";
