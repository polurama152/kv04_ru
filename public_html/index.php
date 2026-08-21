<?php

use Kv04\Diary\Auth;
use Kv04\Diary\Installer;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$kv04DiaryLoaded = false;
$kv04DiaryLoad = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/load.php';
if (is_file($kv04DiaryLoad))
{
	require_once $kv04DiaryLoad;
	$kv04DiaryLoaded = kv04DiaryLoadModule();
}

$APPLICATION->SetTitle('Мой дневник');

$kv04DiaryThemeCss = '/local/modules/kv04.diary/assets/diary-theme.css';
$kv04DiaryComponentCss = '/local/components/kv04/diary.pin/templates/.default/style.css';
$kv04DiaryFeed = $kv04DiaryLoaded && Auth::isLoggedIn();
if ($kv04DiaryFeed)
{
	$kv04DiaryComponentCss = '/local/components/kv04/diary.feed/templates/.default/style.css';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="<?= defined('SITE_CHARSET') ? SITE_CHARSET : 'UTF-8' ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0e1621">
	<meta name="color-scheme" content="dark">
	<title><?php $APPLICATION->ShowTitle(false) ?></title>
	<link rel="stylesheet" href="<?= htmlspecialcharsbx($kv04DiaryThemeCss) ?>">
	<?php if ($kv04DiaryLoaded): ?>
	<link rel="stylesheet" href="<?= htmlspecialcharsbx($kv04DiaryComponentCss) ?>">
	<?php endif; ?>
	<?php if ($kv04DiaryFeed): ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
	<?php endif; ?>
</head>
<body class="kv04-diary-body">
<div class="kv04-diary">
<div class="kv04-diary__inner">
<?php
if (!$kv04DiaryLoaded)
{
	?>
	<div class="kv04-diary__error">Модуль дневника не найден. Загрузите каталог local/modules/kv04.diary/ на сервер.</div>
	<?php
}
else
{
	Installer::ensure();

	if (!Auth::isLoggedIn())
	{
		$APPLICATION->IncludeComponent('kv04:diary.pin', '.default', [], false);
	}
	else
	{
		$APPLICATION->IncludeComponent('kv04:diary.feed', '.default', [
			'CACHE_TYPE' => 'N',
		], false);
	}
}
?>
</div>
</div>
</body>
</html>
<?php
// epilog_after не подключаем: на сервере дублируется класс main (main.broken.*),
// а для дневника достаточно prolog_before — sessid, сессия и компоненты работают.
