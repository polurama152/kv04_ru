<?php

namespace Kv04\Diary;

use Bitrix\Main\Config\Option;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UrlRewriter;

/**
 * Путь, по которому открывается дневник: пусто — корень сайта, 'day' —
 * страница /day. Папки на диске нет: несуществующие адреса .htaccess заводит
 * в bitrix/urlrewrite.php, а наши именованные правила указывают в pub/.
 */
class Path
{
	public const OPTION = 'path';
	public const OPTION_OWNER_SETTINGS = 'owner_settings';
	/** Имя всех rewrite-правил модуля — по нему же они и снимаются. */
	private const RULE_ID = 'kv04.diary';
	/** Занято движком и самим дневником (/d/<токен> — глобальный). */
	private const RESERVED = ['bitrix', 'local', 'upload', 'd'];

	public static function raw(): string
	{
		return (string)Option::get(Installer::MODULE_ID, self::OPTION, '');
	}

	/** '/day' или '' — для склейки адресов без хвостового слэша. */
	public static function base(): string
	{
		$path = self::raw();

		return $path === '' ? '' : '/' . $path;
	}

	/** '/day/' или '/' — адрес страницы дневника. */
	public static function url(): string
	{
		return self::base() . '/';
	}

	/** Владельцам пина разрешено менять настройки из приложения. */
	public static function ownerSettingsAllowed(): bool
	{
		return Option::get(Installer::MODULE_ID, self::OPTION_OWNER_SETTINGS, 'N') === 'Y';
	}

	/**
	 * Канон ввода: слэши по краям и пробелы долой, нижний регистр.
	 * null — путь не годится: символы вне [a-z0-9_-] с '/', или первый
	 * сегмент из зарезервированных.
	 */
	public static function normalize(string $input): ?string
	{
		$path = strtolower(trim($input, " \t\n\r/"));
		if ($path === '')
		{
			return '';
		}
		if (!preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $path))
		{
			return null;
		}
		if (in_array(explode('/', $path, 2)[0], self::RESERVED, true))
		{
			return null;
		}

		return $path;
	}

	/**
	 * Файл или каталог с тем же именем перехватил бы адрес раньше правила:
	 * .htaccess отдаёт в urlrewrite только несуществующие пути.
	 */
	public static function collides(string $path): bool
	{
		return $path !== ''
			&& file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . explode('/', $path, 2)[0]);
	}

	/**
	 * Сохраняет путь и перекладывает правила. Возвращает нормализованный
	 * путь или null, если ввод не прошёл проверку (тогда ничего не менялось).
	 */
	public static function save(string $input): ?string
	{
		$path = self::normalize($input);
		if ($path === null)
		{
			return null;
		}

		Option::set(Installer::MODULE_ID, self::OPTION, $path);
		self::applyRewrite();

		return $path;
	}

	/**
	 * Перекладывает rewrite-правила под текущее значение опции. Правила
	 * именованные, поэтому вызов идемпотентен: старые сначала снимаются.
	 * REQUEST_URI в urlrewrite приходит с query-хвостом — он учтён в маске.
	 */
	public static function applyRewrite(): void
	{
		$siteId = self::siteId();
		UrlRewriter::delete($siteId, ['ID' => self::RULE_ID]);

		$quoted = preg_quote(self::base(), '#');
		$rules = [];
		if (self::base() !== '')
		{
			// На корне дневник рисует физический index.php — правило страницы
			// не нужно.
			$rules[] = ['CONDITION' => '#^' . $quoted . '/?(\?.*)?$#', 'PATH' => '/local/modules/kv04.diary/pub/index.php'];
		}
		$rules[] = ['CONDITION' => '#^' . $quoted . '/sw\.js(\?.*)?$#', 'PATH' => '/local/modules/kv04.diary/pub/sw.php'];
		$rules[] = ['CONDITION' => '#^' . $quoted . '/manifest\.webmanifest(\?.*)?$#', 'PATH' => '/local/modules/kv04.diary/pub/manifest.php'];

		foreach ($rules as $rule)
		{
			UrlRewriter::add($siteId, $rule + ['ID' => self::RULE_ID, 'SORT' => 100]);
		}
	}

	/** Правила пишутся в urlrewrite.php сайта по умолчанию, не языка админки. */
	private static function siteId(): string
	{
		$site = SiteTable::getRow([
			'filter' => ['=DEF' => 'Y', '=ACTIVE' => 'Y'],
			'cache' => ['ttl' => 3600],
		]);
		if ($site)
		{
			return (string)$site['LID'];
		}

		return defined('SITE_ID') ? SITE_ID : 's1';
	}
}
