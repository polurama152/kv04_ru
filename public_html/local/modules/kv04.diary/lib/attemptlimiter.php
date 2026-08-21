<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;

/**
 * Лестница блокировок за неудачные попытки входа.
 *
 * Ключ логический: `acc:<ownerId>` для аккаунта, `ip:<адрес>` для грубого
 * предохранителя. Наружу ключ не хранится — в таблицу уезжает HMAC от него,
 * так что по дампу нельзя восстановить ни IP посетителя, ни владельца.
 *
 * Счётчики держим в своей таблице, а не в `b_option`: Bitrix при первом
 * Option::get тянет ВСЕ опции модуля одним SELECT в память, а любая запись
 * опции чистит кэш модуля целиком. PinService::pepper() дёргается на каждом
 * запросе, поэтому счётчики в опциях делали бы кэш холодным для всех
 * посетителей на каждую неудачную попытку.
 */
class AttemptLimiter
{
	public const TABLE = 'kv04_diary_attempts';

	/**
	 * Номер неудачи => срок блокировки в секундах.
	 * Попытки между ступенями проходят без блокировки.
	 *
	 * Лестницы разные по области: аккаунт принадлежит одному человеку, а за
	 * одним IP сидит целый офис или сотовый оператор. Пока пин остаётся
	 * глобальным идентификатором, обе лестницы одинаково строгие — IP сейчас
	 * единственная защита. Как только появится счётчик на аккаунт, IP надо
	 * будет ослабить до порогов «это уже перебор», иначе чужие опечатки
	 * начнут запирать соседей по адресу.
	 */
	private const STEPS = [
		'acc' => [3 => 300, 6 => 1800, 9 => 3600, 12 => 86400],
		'ip' => [3 => 300, 6 => 1800, 9 => 3600, 12 => 86400],
	];

	/** После последней ступени каждая следующая ошибка снова стоит сутки. */
	private const MAX_LOCK = 86400;

	/** Счётчик забывается после суток без ошибок — иначе владелец, изредка
	 *  промахивающийся мимо пина, однажды упрётся в верхнюю ступень. */
	private const DECAY = 86400;

	/** Раз в сколько вызовов подчищаем протухшие строки. */
	private const CLEANUP_ODDS = 50;

	/**
	 * @return array{locked: bool, until: int, fails: int, wait: int}
	 */
	public static function state(string $key): array
	{
		$row = self::row($key);
		$now = time();

		if (!$row)
		{
			return ['locked' => false, 'until' => 0, 'fails' => 0, 'wait' => 0];
		}

		$until = (int)$row['LOCKED_UNTIL'];
		if ($until > $now)
		{
			return ['locked' => true, 'until' => $until, 'fails' => (int)$row['FAILS'], 'wait' => $until - $now];
		}

		// Блокировка отпустила и с последней ошибки прошли сутки — начинаем с нуля.
		if ((int)$row['LAST_FAIL'] + self::DECAY <= $now)
		{
			return ['locked' => false, 'until' => 0, 'fails' => 0, 'wait' => 0];
		}

		return ['locked' => false, 'until' => 0, 'fails' => (int)$row['FAILS'], 'wait' => 0];
	}

	public static function isLocked(string $key): bool
	{
		return self::state($key)['locked'];
	}

	/**
	 * Засчитывает неудачу и возвращает состояние уже после неё.
	 *
	 * @return array{locked: bool, until: int, fails: int, wait: int}
	 */
	public static function registerFail(string $key): array
	{
		$now = time();
		$before = self::state($key);
		$fails = $before['fails'] + 1;
		$lock = self::lockFor(self::scope($key), $fails);
		// Действующий бан не укорачиваем — берём больший из двух сроков.
		$until = max($before['until'], $lock > 0 ? $now + $lock : 0);

		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();
		$hash = $helper->forSql(self::hash($key));

		// Апсерт одним запросом: две параллельные попытки не потеряют счёт.
		//
		// GREATEST обязателен. Попытки между ступенями дают $until = 0, и без
		// него такая попытка ЗАТИРАЛА БЫ действующий бан: 3-я ставила пять
		// минут, 4-я тут же снимала. Срок блокировки может только расти.
		$connection->queryExecute(sprintf(
			'INSERT INTO %s (LOCK_KEY, FAILS, LOCKED_UNTIL, LAST_FAIL) VALUES (\'%s\', %d, %d, %d) '
			. 'ON DUPLICATE KEY UPDATE FAILS = %d, LOCKED_UNTIL = GREATEST(LOCKED_UNTIL, %d), LAST_FAIL = %d',
			self::TABLE,
			$hash,
			$fails,
			$until,
			$now,
			$fails,
			$until,
			$now
		));

		if (random_int(1, self::CLEANUP_ODDS) === 1)
		{
			self::cleanup();
		}

		return [
			'locked' => $until > $now,
			'until' => $until,
			'fails' => $fails,
			'wait' => $until > $now ? $until - $now : 0,
		];
	}

	public static function reset(string $key): void
	{
		$connection = Application::getConnection();
		$connection->queryExecute(sprintf(
			'DELETE FROM %s WHERE LOCK_KEY = \'%s\'',
			self::TABLE,
			$connection->getSqlHelper()->forSql(self::hash($key))
		));
	}

	/** Убирает строки, где счётчик уже протух и блокировка отпустила. */
	public static function cleanup(): void
	{
		$now = time();
		Application::getConnection()->queryExecute(sprintf(
			'DELETE FROM %s WHERE LOCKED_UNTIL <= %d AND LAST_FAIL <= %d',
			self::TABLE,
			$now,
			$now - self::DECAY
		));
	}

	/** Человеческий срок блокировки для сообщения пользователю. */
	public static function describeWait(int $seconds): string
	{
		if ($seconds <= 0)
		{
			return '';
		}
		if ($seconds < 3600)
		{
			return max(1, (int)ceil($seconds / 60)) . ' мин.';
		}
		if ($seconds < 86400)
		{
			return max(1, (int)round($seconds / 3600)) . ' ч.';
		}

		return max(1, (int)ceil($seconds / 86400)) . ' сут.';
	}

	public static function accountKey(string $ownerId): string
	{
		return 'acc:' . $ownerId;
	}

	public static function ipKey(string $ip): string
	{
		return 'ip:' . $ip;
	}

	private static function lockFor(string $scope, int $fails): int
	{
		$steps = self::STEPS[$scope] ?? self::STEPS['ip'];

		if (isset($steps[$fails]))
		{
			return $steps[$fails];
		}

		return $fails > array_key_last($steps) ? self::MAX_LOCK : 0;
	}

	/** Область берём из префикса ключа: `acc:` или `ip:`. */
	private static function scope(string $key): string
	{
		$position = strpos($key, ':');

		return $position === false ? 'ip' : substr($key, 0, $position);
	}

	private static function row(string $key): ?array
	{
		$connection = Application::getConnection();
		$row = $connection->query(sprintf(
			'SELECT FAILS, LOCKED_UNTIL, LAST_FAIL FROM %s WHERE LOCK_KEY = \'%s\'',
			self::TABLE,
			$connection->getSqlHelper()->forSql(self::hash($key))
		))->fetch();

		return $row ?: null;
	}

	private static function hash(string $key): string
	{
		return hash_hmac('sha256', $key, PinService::pepper());
	}
}
