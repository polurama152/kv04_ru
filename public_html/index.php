<?php

use Kv04\Diary\Path;

/**
 * Корень сайта. Дневник живёт на пути из настройки модуля (Path): пока путь
 * пуст — рисуется прямо здесь, после переезда корень отвечает вечным
 * редиректом, а страницу дневника собирает bitrix/urlrewrite.php.
 */
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$kv04DiaryLoaded = false;
$kv04DiaryLoad = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/load.php';
if (is_file($kv04DiaryLoad))
{
	require_once $kv04DiaryLoad;
	$kv04DiaryLoaded = kv04DiaryLoadModule();
}

// Страница по ссылке (?d=) отвечает с корня при любом пути дневника:
// правило /d/ в .htaccess глобальное, розданные ссылки не ломаются.
$kv04DiaryBase = $kv04DiaryLoaded ? Path::base() : '';
if ($kv04DiaryBase !== '' && (string)($_GET['d'] ?? '') === '')
{
	// Location относительный: LocalRedirect склеил бы абсолютный адрес по
	// схеме Apache, а он за nginx всегда видит http — вышел бы лишний
	// прыжок через незашифрованный адрес (ловушка площадки, спека 0002).
	CHTTP::SetStatus('301 Moved Permanently');
	header('Location: ' . $kv04DiaryBase . '/');
	die();
}

require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/pub/index.php';
