<?php
// Stands in for the LoginLlama API in ApiTest. The path picks the answer.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/bad-gateway/login/check') {
    http_response_code(502);
    echo 'Bad Gateway';
} elseif ($path === '/slow/login/check') {
    sleep(5);
    echo '{}';
} elseif ($path === '/quota/login/check') {
    http_response_code(402);
    echo json_encode(['errors' => [['code' => 'quota_exceeded', 'title' => 'Quota']]]);
}
