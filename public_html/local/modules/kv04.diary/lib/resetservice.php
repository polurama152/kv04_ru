<?php

namespace Kv04\Diary;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Mail\Event as MailEvent;

/**
 * Возврат доступа, когда пин забыт. Единственный путь назад — почта, поэтому
 * дневник требует привязать её перед сменой пина: человек, меняющий ключ,
 * обязан иметь запасной.
 *
 * Ссылка из письма НЕ пускает внутрь: она открывает форму нового пина и
 * ничего больше. Так утёкшее письмо не читает записи тихо — владелец увидит,
 * что прежний пин перестал подходить, и поймёт, что происходит.
 *
 * Токен в базе лежит хэшем: дамп таблицы не должен превращаться в пачку
 * рабочих ссылок. По той же причине письмо уходит одно на две минуты, а все
 * прежние ссылки владельца гасятся при выдаче новой.
 *
 * Ответ на запрос всегда один и тот же — «если дневник есть и почта
 * привязана, письмо ушло». Разный ответ позволял бы перечислять чужие адреса
 * и проверять, заведён ли дневник на известную почту.
 */
class ResetService
{
	public const TABLE = 'kv04_diary_resets';

	/** Тип почтового события; шаблон к нему заводит Installer. */
	public const EVENT_NAME = 'KV04_DIARY_PIN_RESET';

	/** Час жизни ссылки: письмо читают не сразу, но и не через сутки. */
	private const TTL = 3600;

	/** Не чаще одного письма в две минуты на дневник. */
	private const COOLDOWN = 120;

	private const SENT_MESSAGE = 'Если такой дневник есть и к нему привязана почта, письмо уже ушло. Ссылка живёт час.';

	/**
	 * Запрос письма. $input — почта или адрес дневника; $slugOwnerId приходит
	 * со страницы личного адреса, где спрашивать нечего: адрес уже сказал,
	 * чей это дневник.
	 */
	public static function request(string $input, ?string $slugOwnerId = null): array
	{
		$ipKey = AttemptLimiter::ipKey(self::remoteAddress());
		$ipState = AttemptLimiter::state($ipKey);
		if ($ipState['locked'])
		{
			return PinService::lockedResult($ipState['wait']);
		}

		$ownerId = $slugOwnerId === null
			? PinService::ownerByLogin($input)
			: ($slugOwnerId === '' ? null : $slugOwnerId);
		$email = $ownerId === null ? '' : PinService::emailFor($ownerId);

		// Промах засчитываем в лестницу по IP — так же, как неудачный вход.
		// Иначе форма стала бы бесплатным перечислением чужих адресов.
		if ($ownerId === null || $email === '')
		{
			AttemptLimiter::registerFail($ipKey);

			return ['ok' => true, 'message' => self::SENT_MESSAGE];
		}

		if (self::sentRecently($ownerId))
		{
			return ['ok' => true, 'message' => self::SENT_MESSAGE];
		}

		$token = bin2hex(random_bytes(32));
		self::dropTokens($ownerId);
		self::store($ownerId, $token);
		self::send($email, self::url($ownerId, $token));

		return ['ok' => true, 'message' => self::SENT_MESSAGE];
	}

	/** Чей дневник открывает ссылка. null — ссылки нет, она протухла или уже сработала. */
	public static function ownerByToken(string $token): ?string
	{
		$row = self::liveRow($token);

		return $row ? (string)$row['OWNER_ID'] : null;
	}

	/**
	 * Новый пин по ссылке из письма. Внутрь не пускаем: дальше человек
	 * набирает свежий пин на обычном пин-паде — так он его сразу и запомнит.
	 */
	public static function complete(string $token, string $pin, string $confirm): array
	{
		$ipKey = AttemptLimiter::ipKey(self::remoteAddress());
		$ipState = AttemptLimiter::state($ipKey);
		if ($ipState['locked'])
		{
			return PinService::lockedResult($ipState['wait']);
		}

		$row = self::liveRow($token);
		if (!$row)
		{
			AttemptLimiter::registerFail($ipKey);

			return ['ok' => false, 'error' => 'Ссылка устарела или уже сработала — запросите новую'];
		}

		$pin = PinService::normalize($pin);
		if (!PinService::isValidFormat($pin) || $pin !== PinService::normalize($confirm))
		{
			return ['ok' => false, 'error' => 'Пины не совпадают'];
		}

		$ownerId = (string)$row['OWNER_ID'];
		if (!PinService::setPin($ownerId, $pin))
		{
			return ['ok' => false, 'error' => 'Не удалось сменить пин'];
		}

		// Ссылку гасим вместе со всеми остальными: письмо одноразовое.
		self::dropTokens($ownerId);
		// Владелец доказал, что почтой владеет он, — счётчик промахов ни к чему.
		AttemptLimiter::reset(AttemptLimiter::accountKey($ownerId));

		return ['ok' => true, 'url' => Path::personalUrl(SlugService::forOwner($ownerId))];
	}

	/** Чистка отработавших строк — вызывается вместе с остальной уборкой. */
	public static function purgeExpired(): void
	{
		$connection = Application::getConnection();
		if (!$connection->isTableExists(self::TABLE))
		{
			return;
		}

		$connection->queryExecute(sprintf(
			'DELETE FROM %s WHERE EXPIRES_AT < %d OR USED_AT > 0',
			self::TABLE,
			time() - self::TTL
		));
	}

	private static function store(string $ownerId, string $token): void
	{
		$now = time();
		Application::getConnection()->queryExecute(sprintf(
			'INSERT INTO %s (OWNER_ID, TOKEN, CREATED_AT, EXPIRES_AT, USED_AT) VALUES (\'%s\', \'%s\', %d, %d, 0)',
			self::TABLE,
			self::escape($ownerId),
			self::escape(self::hash($token)),
			$now,
			$now + self::TTL
		));
	}

	private static function liveRow(string $token): ?array
	{
		$token = trim($token);
		if ($token === '')
		{
			return null;
		}

		$row = Application::getConnection()->query(sprintf(
			'SELECT ID, OWNER_ID FROM %s WHERE TOKEN = \'%s\' AND USED_AT = 0 AND EXPIRES_AT > %d LIMIT 1',
			self::TABLE,
			self::escape(self::hash($token)),
			time()
		))->fetch();

		return $row ?: null;
	}

	private static function sentRecently(string $ownerId): bool
	{
		$row = Application::getConnection()->query(sprintf(
			'SELECT ID FROM %s WHERE OWNER_ID = \'%s\' AND USED_AT = 0 AND CREATED_AT > %d LIMIT 1',
			self::TABLE,
			self::escape($ownerId),
			time() - self::COOLDOWN
		))->fetch();

		return (bool)$row;
	}

	private static function dropTokens(string $ownerId): void
	{
		Application::getConnection()->queryExecute(sprintf(
			'UPDATE %s SET USED_AT = %d WHERE OWNER_ID = \'%s\' AND USED_AT = 0',
			self::TABLE,
			time(),
			self::escape($ownerId)
		));
	}

	/**
	 * Ссылка ведёт на личный адрес владельца, если он есть: письмо возвращает
	 * человека в то же приложение, которое стоит у него на телефоне.
	 */
	private static function url(string $ownerId, string $token): string
	{
		$host = (string)($_SERVER['HTTP_HOST'] ?? 'kv04.ru');

		return 'https://' . $host . Path::personalUrl(SlugService::forOwner($ownerId)) . '?reset=' . $token;
	}

	/**
	 * Письмо уходит немедленно, а не через очередь b_event: страница дневника
	 * не поднимает эпилог (см. pub/index.php), AJAX и вовсе заканчивается
	 * die(), а агенты на площадке не крутятся — очередь никто бы не разобрал.
	 */
	private static function send(string $email, string $url): bool
	{
		$site = defined('SITE_ID') ? SITE_ID : 's1';
		$result = MailEvent::sendImmediate([
			'EVENT_NAME' => self::EVENT_NAME,
			'LID' => $site,
			'C_FIELDS' => [
				'EMAIL_TO' => $email,
				'RESET_URL' => $url,
				'DIARY_URL' => 'https://' . (string)($_SERVER['HTTP_HOST'] ?? 'kv04.ru') . Path::url(),
				'TTL_MINUTES' => (int)(self::TTL / 60),
			],
		]);

		return $result === MailEvent::SEND_RESULT_SUCCESS;
	}

	private static function hash(string $token): string
	{
		return hash_hmac('sha256', $token, PinService::pepper());
	}

	private static function escape(string $value): string
	{
		return Application::getConnection()->getSqlHelper()->forSql($value);
	}

	private static function remoteAddress(): string
	{
		return (string)Context::getCurrent()->getRequest()->getRemoteAddress();
	}
}
