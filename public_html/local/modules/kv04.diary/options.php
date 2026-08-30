<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Kv04\Diary\Path;

/**
 * Настройки модуля: их открывает bitrix/admin/settings.php?mid=kv04.diary.
 * То же самое умеет панель «Настройки» в самом дневнике — обе точки пишут
 * одни опции и зовут один Path::applyRewrite(), поэтому действуют
 * параллельно. Флаг owner_settings меняется только здесь: из приложения
 * владелец не может выдать права сам себе.
 */

/** @global CMain $APPLICATION */
global $APPLICATION;

$module_id = 'kv04.diary';

if ($APPLICATION->GetGroupRight($module_id) < 'W')
{
	$APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

IncludeModuleLangFile(__FILE__);
Loader::includeModule($module_id);

$kv04OptError = '';
$kv04OptNote = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['Update'] ?? '') !== '' && check_bitrix_sessid())
{
	$kv04OptSaved = Path::save((string)($_POST['KV04_DIARY_PATH'] ?? ''));
	if ($kv04OptSaved === null)
	{
		$kv04OptError = GetMessage('KV04_DIARY_OPT_PATH_INVALID');
	}
	else
	{
		Option::set($module_id, Path::OPTION_OWNER_SETTINGS, ($_POST['KV04_DIARY_OWNER_SETTINGS'] ?? '') === 'Y' ? 'Y' : 'N');
		if (Path::collides($kv04OptSaved))
		{
			$kv04OptNote = GetMessage('KV04_DIARY_OPT_PATH_COLLISION', ['#SEGMENT#' => explode('/', $kv04OptSaved, 2)[0]]);
		}
	}
}

$kv04OptPath = Path::raw();
$kv04OptOwner = Path::ownerSettingsAllowed();

if ($kv04OptError !== '')
{
	CAdminMessage::ShowMessage(['MESSAGE' => $kv04OptError, 'TYPE' => 'ERROR']);
}
if ($kv04OptNote !== '')
{
	CAdminMessage::ShowMessage(['MESSAGE' => $kv04OptNote, 'TYPE' => 'OK']);
}

$kv04OptTabs = new CAdminTabControl('kv04DiaryOptTabs', [
	[
		'DIV' => 'edit1',
		'TAB' => GetMessage('KV04_DIARY_OPT_TAB'),
		'TITLE' => GetMessage('KV04_DIARY_OPT_TAB_TITLE'),
	],
]);
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?mid=<?= urlencode($module_id) ?>&lang=<?= LANGUAGE_ID ?>">
<?= bitrix_sessid_post() ?>
<?php
$kv04OptTabs->Begin();
$kv04OptTabs->BeginNextTab();
?>
	<tr>
		<td width="40%"><label for="kv04-diary-path"><?= GetMessage('KV04_DIARY_OPT_PATH') ?></label></td>
		<td width="60%">
			<input type="text" id="kv04-diary-path" name="KV04_DIARY_PATH" value="<?= htmlspecialcharsbx($kv04OptPath) ?>" size="30" placeholder="day">
		</td>
	</tr>
	<tr>
		<td></td>
		<td><?= GetMessage('KV04_DIARY_OPT_PATH_HINT') ?></td>
	</tr>
	<tr>
		<td><label for="kv04-diary-owner"><?= GetMessage('KV04_DIARY_OPT_OWNER') ?></label></td>
		<td>
			<input type="checkbox" id="kv04-diary-owner" name="KV04_DIARY_OWNER_SETTINGS" value="Y"<?= $kv04OptOwner ? ' checked' : '' ?>>
		</td>
	</tr>
	<tr>
		<td></td>
		<td><?= GetMessage('KV04_DIARY_OPT_OWNER_HINT') ?></td>
	</tr>
<?php
$kv04OptTabs->Buttons();
?>
	<input type="submit" name="Update" value="<?= GetMessage('KV04_DIARY_OPT_SAVE') ?>" class="adm-btn-save">
<?php
$kv04OptTabs->End();
?>
</form>
