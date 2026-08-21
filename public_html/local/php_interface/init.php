<?php

$kv04DiaryLoad = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/load.php';
if (is_file($kv04DiaryLoad))
{
	require_once $kv04DiaryLoad;
	kv04DiaryLoadModule();
}
else
{
	$kv04DiaryDir = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/';
	if (is_file($kv04DiaryDir . 'boot.php'))
	{
		require_once $kv04DiaryDir . 'boot.php';
	}
	elseif (is_file($kv04DiaryDir . 'include.php'))
	{
		require_once $kv04DiaryDir . 'include.php';
	}
}
