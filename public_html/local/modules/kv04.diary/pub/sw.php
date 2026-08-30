<?php

/**
 * Отдаёт сервис-воркер под адресом <путь дневника>/sw.js: scope воркера
 * определяется URL ответа, а bitrix/urlrewrite.php включает только .php —
 * поэтому JS едет через этого посредника. Ядро Bitrix здесь не поднимается:
 * файлу не нужно ничего, кроме самого воркера.
 */
$kv04SwFile = dirname(__DIR__) . '/assets/pwa/sw.js';
if (!is_file($kv04SwFile))
{
	http_response_code(404);
	die();
}

header('Content-Type: application/javascript; charset=UTF-8');
// Новый воркер должен подхватываться сразу, без ожидания суточной сверки.
header('Cache-Control: no-cache');
header('Content-Length: ' . filesize($kv04SwFile));
readfile($kv04SwFile);
die();
