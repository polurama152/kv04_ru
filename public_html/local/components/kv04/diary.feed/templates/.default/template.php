<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

// Разметку заметок рисует общий файл модуля: ту же ленту показывает
// страница, открытая по ссылке, и второй экземпляр этой разметки
// разъехался бы с первым на первой же правке.
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/include/render-items.php';

$kv04Books = $arResult['BOOKS'] ?? [];
$kv04CurrentBook = (int)($arResult['CURRENT_BOOK'] ?? 0);
$kv04CurrentTitle = 'Мой дневник';
foreach ($kv04Books as $kv04Book)
{
	if ((int)$kv04Book['id'] === $kv04CurrentBook)
	{
		$kv04CurrentTitle = (string)$kv04Book['title'];
	}
}
?>
<div class="kv04-workspace" id="kv04-workspace">

<aside class="kv04-books" data-books aria-label="Дневники">
	<div class="kv04-books__head">
		<span class="kv04-books__title">Дневники</span>
		<button type="button" class="kv04-books__close" data-books-close aria-label="Закрыть список">&times;</button>
	</div>
	<div class="kv04-books__list" data-books-list>
		<?php foreach ($kv04Books as $kv04Book): ?>
			<?php $kv04IsCurrent = (int)$kv04Book['id'] === $kv04CurrentBook; ?>
			<div class="kv04-book<?=$kv04IsCurrent ? ' is-current' : ''?>" data-book="<?=(int)$kv04Book['id']?>">
				<button type="button" class="kv04-book__open" data-book-open title="<?=htmlspecialcharsbx($kv04Book['title'])?><?=$kv04IsCurrent ? ' — нажмите, чтобы переименовать' : ''?>"><?=htmlspecialcharsbx($kv04Book['title'])?></button>
				<button type="button" class="kv04-book__act kv04-book__act--danger" data-book-delete aria-label="Удалить дневник" title="Удалить дневник">&times;</button>
			</div>
		<?php endforeach; ?>
	</div>
	<button type="button" class="kv04-books__add" data-book-add>+ Новый дневник</button>
	<p class="kv04-books__limit" data-books-limit hidden></p>
	<?php if (!empty($arResult['SHOW_SETTINGS'])): ?>
		<button type="button" class="kv04-books__settings" data-settings-open>Настройки</button>
	<?php endif; ?>
	<?php if (!empty($arResult['SHOW_ADMIN_LINK'])): ?>
		<a class="kv04-books__admin" href="/bitrix/admin/">Админка</a>
	<?php endif; ?>
</aside>

<div class="kv04-books__backdrop" data-books-backdrop></div>

<div class="kv04-feed" id="kv04-feed"
	data-max-books="<?=(int)($arResult['MAX_BOOKS'] ?? 50)?>"
	data-sessid="<?=htmlspecialcharsbx($arResult['SESSID'])?>"
	data-share-url="<?=htmlspecialcharsbx((string)($arResult['SHARE_URL'] ?? ''))?>">
	<div class="kv04-feed__head">
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm kv04-feed__books" data-books-open>Дневники</button>
		<h1 data-current-title title="Нажмите, чтобы переименовать"><?=htmlspecialcharsbx($kv04CurrentTitle)?></h1>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-share-open>Поделиться</button>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-trash-open>Корзина</button>
		<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-logout>Выйти</button>
	</div>

	<div class="kv04-share" data-share hidden>
		<div class="kv04-share__head">
			<span class="kv04-share__title">Ссылка на этот дневник</span>
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-share-close>Закрыть</button>
		</div>
		<p class="kv04-share__note">Ссылка живая: всё, что появится в этом дневнике дальше, тоже увидит тот, у кого она есть.</p>
		<input type="text" class="kv04-input kv04-share__url" data-share-url readonly spellcheck="false" aria-label="Ссылка">
		<div class="kv04-share__actions">
			<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-share-send>Отправить</button>
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-share-copy>Скопировать</button>
			<button type="button" class="kv04-btn kv04-btn--danger kv04-btn--sm" data-share-revoke>Закрыть доступ</button>
		</div>
	</div>

<?php if (!empty($arResult['SHOW_SETTINGS'])): ?>
	<div class="kv04-settings" data-settings hidden>
		<div class="kv04-settings__head">
			<span class="kv04-settings__title">Настройки</span>
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-settings-close>Закрыть</button>
		</div>
		<label class="kv04-settings__label" for="kv04-settings-path">Путь дневника</label>
		<div class="kv04-settings__row">
			<span class="kv04-settings__origin"><?=htmlspecialcharsbx((string)($_SERVER['HTTP_HOST'] ?? ''))?>/</span>
			<input type="text" id="kv04-settings-path" class="kv04-input kv04-settings__path" data-settings-path
				value="<?=htmlspecialcharsbx((string)($arResult['DIARY_PATH'] ?? ''))?>" placeholder="day"
				spellcheck="false" autocomplete="off" autocapitalize="off">
			<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-settings-save>Сохранить</button>
		</div>
		<p class="kv04-settings__note">Пусто — дневник на главной странице. Например, day — дневник переезжает
			на /day, а главная отвечает редиректом. Папку создавать не нужно: адрес виртуальный.</p>
		<p class="kv04-settings__status" data-settings-status hidden></p>
	</div>
<?php endif; ?>

	<div class="kv04-trash" data-trash hidden>
		<div class="kv04-trash__head">
			<span class="kv04-trash__title">Корзина</span>
			<span class="kv04-trash__note">Хранится <?=(int)$arResult['TRASH_DAYS']?> дней, потом удаляется насовсем</span>
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-trash-close>Закрыть</button>
		</div>
		<div class="kv04-trash__list" data-trash-list></div>
	</div>

<?php if (!empty($arResult['NEEDS_EMAIL'])): ?>
	<div class="kv04-attach-email" data-attach-email>
		<p class="kv04-attach-email__text">
			Привяжите почту — она станет именем дневника.
			Пока её нет, дневник открывается одним пином, а такой пин может
			случайно подойти постороннему.
		</p>
		<div class="kv04-attach-email__row">
			<input type="email" class="kv04-input" autocomplete="email" inputmode="email"
				spellcheck="false" data-attach-input placeholder="you@example.com" aria-label="Почта">
			<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-attach-save>Привязать</button>
		</div>
		<p class="kv04-attach-email__status" data-attach-status hidden></p>
	</div>
<?php endif; ?>

	<form class="kv04-composer" data-composer>
		<div class="kv04-note__body kv04-composer__input"
			data-input
			contenteditable="true"
			role="textbox"
			aria-multiline="true"
			aria-label="Текст заметки"
			data-placeholder="Что у вас на уме? Можно код и файлы."></div>
		<div class="kv04-composer__bar">
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-code>Код</button>
			<label class="kv04-btn kv04-btn--muted kv04-btn--sm">
				Файл
				<input type="file" name="media[]" accept="image/*,video/mp4,video/webm" multiple hidden>
			</label>
			<label class="kv04-btn kv04-btn--muted kv04-btn--sm" data-shot title="Снять и сразу сохранить">
				Фото
				<input type="file" accept="image/*" capture="environment" hidden>
			</label>
			<label class="kv04-btn kv04-btn--muted kv04-btn--sm" data-clip title="Записать видео — в дневник ляжет короткое превью">
				Видео
				<input type="file" accept="video/*" capture="environment" hidden>
			</label>
			<button type="submit" class="kv04-btn kv04-btn--primary kv04-btn--sm" title="Готово (Ctrl+Enter)">Готово</button>
		</div>
		<div class="kv04-composer__preview" data-file-preview hidden></div>
		<p class="kv04-composer__status" data-shot-status hidden></p>
		<p class="kv04-feed__error" data-error hidden></p>
	</form>

	<div class="kv04-feed__list" data-list>
		<?php kv04DiaryRenderItems($arResult['ITEMS']); ?>
	</div>
</div>

</div><!-- /kv04-workspace -->

<div class="kv04-lightbox" id="kv04-lightbox" aria-hidden="true">
	<div class="kv04-lightbox__backdrop" data-lightbox-close tabindex="-1"></div>
	<button type="button" class="kv04-lightbox__close" data-lightbox-close aria-label="Закрыть">&times;</button>
	<div class="kv04-lightbox__stage" role="dialog" aria-modal="true" aria-label="Просмотр медиа"></div>
</div>

<div class="kv04-confirm" id="kv04-confirm" aria-hidden="true" hidden>
	<div class="kv04-confirm__backdrop"></div>
	<div class="kv04-confirm__box" role="alertdialog" aria-modal="true" aria-labelledby="kv04-confirm-title">
		<p class="kv04-confirm__title" id="kv04-confirm-title">Сохранить изменения?</p>
		<div class="kv04-confirm__actions">
			<button type="button" class="kv04-btn kv04-btn--muted kv04-btn--sm" data-confirm-no>Нет</button>
			<button type="button" class="kv04-btn kv04-btn--primary kv04-btn--sm" data-confirm-yes>Да</button>
		</div>
	</div>
</div>

<?php
/**
 * highlight.js лежит рядом с модулем, а не на cdnjs: сторонний домен стоил
 * браузеру отдельных DNS и TLS на первой загрузке дневника, до того как
 * покажется хоть строчка. Метка mtime даёт новый URL, когда файл обновится, —
 * так же, как у стилей в index.php.
 */
$kv04HljsSrc = '/local/modules/kv04.diary/assets/highlight/highlight.min.js';
$kv04HljsVersion = (int)@filemtime($_SERVER['DOCUMENT_ROOT'] . $kv04HljsSrc);
?>
<script src="<?=htmlspecialcharsbx($kv04HljsSrc . ($kv04HljsVersion > 0 ? '?v=' . $kv04HljsVersion : ''))?>"></script>
<?php
/**
 * Скрипт ленты лежит отдельным файлом рядом с шаблоном. Метка mtime — как у
 * стилей и highlight.js: кэш длинный, а правка доходит до вернувшегося
 * посетителя сразу.
 */
$kv04FeedJs = '/local/components/kv04/diary.feed/templates/.default/script.js';
$kv04FeedJsVersion = (int)@filemtime($_SERVER['DOCUMENT_ROOT'] . $kv04FeedJs);
?>
<script src="<?=htmlspecialcharsbx($kv04FeedJs . ($kv04FeedJsVersion > 0 ? '?v=' . $kv04FeedJsVersion : ''))?>" defer></script>
