<?php

use Kv04\Diary\Auth;
use Kv04\Diary\Installer;
use Kv04\Diary\Path;
use Kv04\Diary\ShareService;
use Kv04\Diary\SlugService;

/**
 * Страница дневника. Сюда приходят двумя дорогами: include из корневого
 * index.php (путь пуст или ?d=) и include из bitrix/urlrewrite.php по
 * правилу настроенного пути — там пролога ещё нет, поднимаем сами.
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
}

$kv04DiaryLoaded = false;
$kv04DiaryLoad = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/load.php';
if (is_file($kv04DiaryLoad))
{
	require_once $kv04DiaryLoad;
	$kv04DiaryLoaded = kv04DiaryLoadModule();
}

/**
 * Дневник, открытый по ссылке. Разбираем до первой строчки разметки: от
 * ответа зависит и заголовок страницы, и код ответа. Несуществующая и
 * отозванная ссылка отвечают одинаково — 404 и короткая страница, чтобы по
 * ответу нельзя было понять, была ли когда-нибудь такая ссылка.
 */
$kv04DiaryToken = (string)($_GET['d'] ?? '');

/**
 * Личный адрес владельца (/<путь>/<адрес>/). Его подставляет rewrite-правило.
 * Владельца по нему находим здесь, чтобы вход обошёлся без почты: адрес
 * говорит, чей дневник, пин — тот ли это человек. Незнакомый адрес не 404 и
 * не редирект: страница обязана выглядеть точно так же, как чужая, иначе по
 * ответу можно было бы перечислить существующие адреса.
 */
$kv04DiarySlug = '';
$kv04DiarySlugOwner = null;
if ($kv04DiaryLoaded && $kv04DiaryToken === '')
{
	$kv04DiarySlugRaw = (string)($_GET['u'] ?? '');
	if ($kv04DiarySlugRaw !== '')
	{
		$kv04DiarySlug = (string)(SlugService::normalize($kv04DiarySlugRaw) ?? '');
		Installer::ensure();
		// Пустая строка вместо null — «адрес в запросе был, но он ничей».
		$kv04DiarySlugOwner = $kv04DiarySlug === ''
			? ''
			: (string)(SlugService::ownerBySlug($kv04DiarySlug) ?? '');

		// Владелец переехал: его приложение приводим на общую страницу.
		// Новый адрес не подставляем намеренно — адрес меняют в том числе
		// чтобы уйти от тех, кто знал прежний.
		if ($kv04DiarySlugOwner === '' && $kv04DiarySlug !== '' && SlugService::isMoved($kv04DiarySlug))
		{
			CHTTP::SetStatus('301 Moved Permanently');
			header('Location: ' . Path::url());
			die();
		}
	}
}

/**
 * Единственный адрес страницы — со слэшем на конце. Без него браузер не
 * считает страницу частью приложения: scope воркера равен «/путь/», а
 * «/путь» лежит вне его — тогда нет ни офлайна, ни предложения установки.
 * Сюда же приходят прежние адреса дневника после переезда, и этот же 301
 * возвращает их владельцам установленный значок.
 *
 * POST не трогаем: 301 превратил бы его в GET и молча съел вход по пину.
 */
$kv04DiaryBase = $kv04DiaryLoaded ? Path::base() : '';
$kv04DiaryCanonical = $kv04DiaryLoaded ? Path::personalUrl($kv04DiarySlug) : '/';
$kv04RequestPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($kv04DiaryBase !== ''
	&& $kv04DiaryToken === ''
	&& ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
	&& $kv04RequestPath !== $kv04DiaryCanonical)
{
	CHTTP::SetStatus('301 Moved Permanently');
	header('Location: ' . $kv04DiaryCanonical);
	die();
}

$kv04DiaryShare = null;
if ($kv04DiaryToken !== '' && $kv04DiaryLoaded)
{
	Installer::ensure();
	$kv04DiaryShare = ShareService::resolve($kv04DiaryToken);
	if (!$kv04DiaryShare)
	{
		CHTTP::SetStatus('404 Not Found');
	}
}

$APPLICATION->SetTitle($kv04DiaryShare ? $kv04DiaryShare['title'] : 'Мой дневник');

// Адреса манифеста и воркера — от канонического адреса этой страницы:
// на личном адресе приложение своё, со своим scope и своим значком.
$kv04DiaryBaseUrl = $kv04DiaryCanonical;

/**
 * Статика отдаётся с Cache-Control на трое суток (mod_expires в .htaccess),
 * поэтому без метки версии правка стилей доходит до вернувшегося посетителя
 * только через трое суток. Подставляем mtime файла: кэш остаётся длинным,
 * но новый файл получает новый URL.
 */
$kv04DiaryAsset = static function (string $path): string {
	$file = $_SERVER['DOCUMENT_ROOT'] . $path;
	$version = is_file($file) ? (int)filemtime($file) : 0;

	return $version > 0 ? $path . '?v=' . $version : $path;
};

$kv04DiaryThemeCss = '/local/modules/kv04.diary/assets/diary-theme.css';
$kv04DiaryHljsCss = '/local/modules/kv04.diary/assets/highlight/atom-one-dark.min.css';
$kv04DiaryComponentCss = '/local/components/kv04/diary.pin/templates/.default/style.css';
$kv04DiaryFeed = $kv04DiaryLoaded && $kv04DiaryToken === '' && Auth::isLoggedIn();
// Заметки на странице по ссылке — те же, значит и стили ленты те же.
if ($kv04DiaryFeed || $kv04DiaryShare)
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
	<link rel="stylesheet" href="<?= htmlspecialcharsbx($kv04DiaryAsset($kv04DiaryThemeCss)) ?>">
	<?php if ($kv04DiaryLoaded): ?>
	<link rel="stylesheet" href="<?= htmlspecialcharsbx($kv04DiaryAsset($kv04DiaryComponentCss)) ?>">
	<?php endif; ?>
	<?php if ($kv04DiaryFeed || $kv04DiaryShare): ?>
	<link rel="stylesheet" href="<?= htmlspecialcharsbx($kv04DiaryAsset($kv04DiaryHljsCss)) ?>">
	<?php endif; ?>
	<?php if ($kv04DiaryToken !== ''): ?>
	<?php /* Личный дневник не должен попасть в поиск, даже если ссылкой
	   поделились в открытом чате и её подобрал робот. */ ?>
	<meta name="robots" content="noindex, nofollow">
	<?php else: ?>
	<?php /* PWA — только своя страница: гостю по share-ссылке установка ни к чему.
	   Манифест и воркер отвечают с пути дневника — от него зависит scope. */ ?>
	<link rel="manifest" href="<?= htmlspecialcharsbx($kv04DiaryBaseUrl) ?>manifest.webmanifest">
	<link rel="apple-touch-icon" href="/local/modules/kv04.diary/assets/pwa/apple-touch-icon.png">
	<meta name="application-name" content="Дневник">
	<meta name="apple-mobile-web-app-title" content="Дневник">
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

	if ($kv04DiaryToken !== '')
	{
		if ($kv04DiaryShare)
		{
			$APPLICATION->IncludeComponent('kv04:diary.share', '.default', [
				'OWNER' => $kv04DiaryShare['owner'],
				'BOOK' => $kv04DiaryShare['book'],
				'TITLE' => $kv04DiaryShare['title'],
				'CACHE_TYPE' => 'N',
			], false);
		}
		else
		{
			?>
			<div class="kv04-diary__error">Такой ссылки нет. Возможно, доступ закрыли.</div>
			<?php
		}
	}
	elseif (!Auth::isLoggedIn())
	{
		$APPLICATION->IncludeComponent('kv04:diary.pin', '.default', [
			'SLUG' => $kv04DiarySlug,
			'SLUG_OWNER' => $kv04DiarySlugOwner,
		], false);
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
<?php if ($kv04DiaryToken === ''): ?>
<script>
	if ('serviceWorker' in navigator) {
		var kv04SwScope = new URL('<?= htmlspecialcharsbx($kv04DiaryBaseUrl) ?>', location.origin).href;
		// Миграция с корня: воркер прежнего пути снимается, иначе его
		// офлайн-фолбэк накрывал бы страницы вне дневника. Чужие воркеры
		// не трогаем — только свои, по имени файла sw.js.
		navigator.serviceWorker.getRegistrations().then(function (regs) {
			regs.forEach(function (reg) {
				var script = reg.active || reg.waiting || reg.installing;
				if (reg.scope !== kv04SwScope && script && /\/sw\.js$/.test(script.scriptURL)) {
					reg.unregister();
				}
			});
		});
		navigator.serviceWorker.register('<?= htmlspecialcharsbx($kv04DiaryBaseUrl) ?>sw.js');
	}
</script>
<?php endif; ?>
</body>
</html>
<?php
// epilog_after не подключаем: на сервере дублируется класс main (main.broken.*),
// а для дневника достаточно prolog_before — sessid, сессия и компоненты работают.
