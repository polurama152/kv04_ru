<?php

/**
 * Манифест PWA. start_url и scope равны пути дневника, а путь берётся из
 * самого адреса запроса: rewrite-правило на manifest.webmanifest существует
 * только для настроенного пути, так что адрес и есть источник истины —
 * ядро Bitrix ради одной опции не поднимаем.
 */
$kv04Uri = (string)($_SERVER['REQUEST_URI'] ?? '/manifest.webmanifest');
$kv04Path = (string)parse_url($kv04Uri, PHP_URL_PATH);
$kv04Base = (string)preg_replace('#/manifest\.webmanifest$#', '', $kv04Path);
$kv04Scope = $kv04Base === '' ? '/' : $kv04Base . '/';

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: no-cache');
echo json_encode([
	'name' => 'Мой дневник',
	'short_name' => 'Дневник',
	'lang' => 'ru',
	'start_url' => $kv04Scope,
	'scope' => $kv04Scope,
	'display' => 'standalone',
	'background_color' => '#0e1621',
	'theme_color' => '#0e1621',
	'icons' => [
		['src' => '/local/modules/kv04.diary/assets/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
		['src' => '/local/modules/kv04.diary/assets/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
		['src' => '/local/modules/kv04.diary/assets/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
	],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
die();
