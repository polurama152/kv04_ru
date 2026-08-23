<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

// Та же разметка заметок, что и в ленте владельца, но в режиме только чтения:
// без крестиков, без правки по клику, без кнопок «Поделиться».
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kv04.diary/include/render-items.php';

$kv04Items = $arResult['ITEMS'] ?? [];
?>
<div class="kv04-feed kv04-feed--shared">
	<div class="kv04-feed__head">
		<h1><?=htmlspecialcharsbx((string)$arResult['TITLE'])?></h1>
		<span class="kv04-shared__badge">Открыт по ссылке</span>
	</div>

	<?php if (!$kv04Items): ?>
		<p class="kv04-shared__empty">В этом дневнике пока пусто.</p>
	<?php else: ?>
		<div class="kv04-feed__list">
			<?php kv04DiaryRenderItems($kv04Items, true); ?>
		</div>
	<?php endif; ?>
</div>

<div class="kv04-lightbox" id="kv04-lightbox" aria-hidden="true">
	<div class="kv04-lightbox__backdrop" data-lightbox-close tabindex="-1"></div>
	<button type="button" class="kv04-lightbox__close" data-lightbox-close aria-label="Закрыть">&times;</button>
	<div class="kv04-lightbox__stage" role="dialog" aria-modal="true" aria-label="Просмотр медиа"></div>
</div>

<?php
/**
 * Подсветка кода нужна и здесь. Стили темы страница подключает вместе со
 * стилями ленты, но красит блоки сама библиотека, а она подключалась только
 * в ленте владельца — по ссылке код оставался серым на тёмной подложке.
 * Файл тот же, что и у ленты, метка mtime — тоже.
 */
$kv04HljsSrc = '/local/modules/kv04.diary/assets/highlight/highlight.min.js';
$kv04HljsVersion = (int)@filemtime($_SERVER['DOCUMENT_ROOT'] . $kv04HljsSrc);
?>
<script src="<?=htmlspecialcharsbx($kv04HljsSrc . ($kv04HljsVersion > 0 ? '?v=' . $kv04HljsVersion : ''))?>"></script>
<script>
(function () {
	// Красим один раз при загрузке: заметки здесь не меняются, значит и
	// перекрашивать нечего.
	if (!window.hljs) return;
	document.querySelectorAll('.kv04-note__body pre code').forEach(function (el) {
		try { hljs.highlightElement(el); } catch (err) {}
	});
})();
</script>

<script>
(function () {
	// Просмотрщик для картинок и видео: на странице по ссылке он тоже нужен,
	// но всё остальное поведение ленты — правка, удаление, отправка — сюда
	// намеренно не приезжает.
	var lightbox = document.getElementById('kv04-lightbox');
	var stage = lightbox && lightbox.querySelector('.kv04-lightbox__stage');
	if (!lightbox || !stage) return;

	function close() {
		var video = stage.querySelector('video');
		if (video) { video.pause(); video.removeAttribute('src'); video.load(); }
		stage.innerHTML = '';
		lightbox.classList.remove('is-open');
		lightbox.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('kv04-lightbox-open');
	}

	function open(type, src) {
		stage.innerHTML = '';
		if (type === 'video') {
			var video = document.createElement('video');
			video.src = src;
			video.controls = true;
			video.playsInline = true;
			video.setAttribute('playsinline', '');
			video.autoplay = true;
			stage.appendChild(video);
			var started = video.play();
			if (started && started.catch) {
				started.catch(function () {
					video.muted = true;
					var again = video.play();
					if (again && again.catch) again.catch(function () {});
				});
			}
		} else {
			var img = document.createElement('img');
			img.src = src;
			img.alt = '';
			stage.appendChild(img);
		}
		lightbox.classList.add('is-open');
		lightbox.setAttribute('aria-hidden', 'false');
		document.body.classList.add('kv04-lightbox-open');
	}

	document.addEventListener('click', function (e) {
		var thumb = e.target.closest('.kv04-media-thumb');
		if (thumb) {
			e.preventDefault();
			open(thumb.getAttribute('data-lightbox'), thumb.getAttribute('data-src'));
			return;
		}
		if (e.target.closest('[data-lightbox-close]') || e.target.matches('.kv04-lightbox__stage img')) {
			close();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && lightbox.classList.contains('is-open')) close();
	});
})();
</script>
