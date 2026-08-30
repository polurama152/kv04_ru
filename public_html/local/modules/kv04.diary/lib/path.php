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
	/** Прежние адреса дневника — с них ведём 301 на нынешний. */
	public const OPTION_LEGACY = 'legacy_paths';
	/** Глубина памяти о переездах: дальше адреса уже никто не помнит. */
	private const LEGACY_LIMIT = 5;
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

	/** Адреса, по которым дневник жил раньше. */
	public static function legacy(): array
	{
		$raw = (string)Option::get(Installer::MODULE_ID, self::OPTION_LEGACY, '');
		if ($raw === '')
		{
			return [];
		}

		$list = json_decode($raw, true);

		return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
	}

	/** Владельцам пина разрешено менять настройки из приложения. */
	public static function ownerSettingsAllowed(): bool
	{
		return Option::get(Installer::MODULE_ID, self::OPTION_OWNER_SETTINGS, 'N') === 'Y';
	}

	/**
	 * Канон ввода: слэши по краям и пробелы долой, нижний регистр.
	 * null — путь не годится: пусто (главная принадлежит сайту, дневнику
	 * нужен свой путь), символы вне [a-z0-9_-] с '/', или первый сегмент
	 * из зарезервированных.
	 */
	public static function normalize(string $input): ?string
	{
		$path = strtolower(trim($input, " \t\n\r/"));
		if ($path === '')
		{
			return null;
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

		self::rememberLegacy(self::raw(), $path);
		Option::set(Installer::MODULE_ID, self::OPTION, $path);
		self::applyRewrite();

		return $path;
	}

	/**
	 * Переезд не должен убивать установленные приложения: прежний адрес
	 * остаётся живым и ведёт 301 на нынешний, поэтому значок на телефоне
	 * продолжает открывать дневник, а не 404.
	 */
	private static function rememberLegacy(string $oldPath, string $newPath): void
	{
		if ($oldPath === '' || $oldPath === $newPath)
		{
			return;
		}

		$list = self::legacy();
		array_unshift($list, $oldPath);
		// Нынешний адрес в списке прежних не место: он обслуживается сам.
		$list = array_diff(array_unique($list), [$newPath]);
		$list = array_slice(array_values($list), 0, self::LEGACY_LIMIT);

		Option::set(Installer::MODULE_ID, self::OPTION_LEGACY, json_encode($list, JSON_UNESCAPED_SLASHES));
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
		$rules = [
			// Страница по ссылке — глобальная при любом пути: розданные
			// /d/<токен> живут вечно. Правило здесь, а не в .htaccess, чтобы
			// корневой index.php оставался чистой страницей сайта.
			['CONDITION' => '#^/d/([0-9a-f]{32})/?(\?.*)?$#', 'RULE' => 'd=$1', 'PATH' => '/local/modules/kv04.diary/pub/index.php'],
		];
		if (self::base() !== '')
		{
			$rules[] = ['CONDITION' => '#^' . $quoted . '/?(\?.*)?$#', 'PATH' => '/local/modules/kv04.diary/pub/index.php'];
		}
		$rules[] = ['CONDITION' => '#^' . $quoted . '/sw\.js(\?.*)?$#', 'PATH' => '/local/modules/kv04.diary/pub/sw.php'];
		$rules[] = ['CONDITION' => '#^' . $quoted . '/manifest\.webmanifest(\?.*)?$#', 'PATH' => '/local/modules/kv04.diary/pub/manifest.php'];

		// Прежние адреса ведут туда же: шелл сам отвечает 301 на нынешний,
		// а вернувшийся оттуда браузер снимает воркер устаревшего scope.
		foreach (self::legacy() as $legacy)
		{
			$rules[] = [
				'CONDITION' => '#^/' . preg_quote($legacy, '#') . '/?(\?.*)?$#',
				'PATH' => '/local/modules/kv04.diary/pub/index.php',
			];
		}

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
