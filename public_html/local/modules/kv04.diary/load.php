<?php

/**
 * Safe entry point for kv04.diary. boot.php always chains to include.php (canonical loader).
 * On Marketplace installs boot.php may be absent — fall back to include.php only.
 */
function kv04DiaryLoadModule(): bool
{
	if (defined('KV04_DIARY_INCLUDED'))
	{
		return true;
	}

	$dir = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/';

	if (is_file($dir . 'boot.php'))
	{
		require_once $dir . 'boot.php';
		return defined('KV04_DIARY_INCLUDED');
	}

	if (is_file($dir . 'include.php'))
	{
		require_once $dir . 'include.php';
		return defined('KV04_DIARY_INCLUDED');
	}

	return false;
}
