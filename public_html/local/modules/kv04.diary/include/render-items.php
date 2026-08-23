<?php
/**
 * Разметка заметок: одна на всех.
 *
 * Ленту владельца и страницу, открытую по ссылке, рисует один и тот же код —
 * иначе третий экземпляр этой разметки разъехался бы с первыми двумя, как уже
 * бывало с крестиками. Разница между ними ровно одна: режим только чтения
 * убирает всё, чем заметку меняют, — крестики и кнопки «Поделиться».
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

if (!function_exists('kv04DiaryRenderBlocks'))
{
	/**
	 * Блок — то, что видно отдельным куском: код либо текст между блоками кода.
	 * Разбор общий с фронтом, поэтому номера блоков совпадают.
	 *
	 * Разметку собираем строкой без единого лишнего пробела: тело заметки
	 * выводится с white-space: pre-wrap и правится как contenteditable, поэтому
	 * отступы шаблона между тегами стали бы частью текста и осели бы в базе
	 * при первом же сохранении.
	 */
	function kv04DiaryRenderBlocks(string $text, bool $readonly = false): string
	{
		$out = '';
		foreach (\Kv04\Diary\NoteService::splitBlocks($text) as $index => $block)
		{
			$out .= '<div class="kv04-note__block" data-block="' . (int)$index . '">'
				. $block['html'];

			if (!$readonly)
			{
				$out .= '<button type="button" class="kv04-block-share" data-share-block'
					. ' aria-label="Поделиться блоком" title="Поделиться">&#8599;</button>'
					. '<button type="button" class="kv04-block-remove" data-block-delete'
					. ' aria-label="Удалить блок" title="Удалить блок">&times;</button>';
			}

			$out .= '</div>';
		}

		return $out;
	}
}

if (!function_exists('kv04DiaryRenderItems'))
{
	function kv04DiaryRenderItems(array $items, bool $readonly = false): void
	{
		foreach ($items as $item)
		{
			$media = $item['media'] ?? [];
			$mediaCount = count($media);
			$gridCount = min(max($mediaCount, 1), 4);
			$extraCount = $mediaCount > 4 ? $mediaCount - 3 : 0;
			?>
			<article class="kv04-note<?=$readonly ? ' kv04-note--readonly' : ''?>" data-id="<?=(int)$item['id']?>">
				<?php if (!empty($item['text'])): ?>
					<div class="kv04-note__body"><?=kv04DiaryRenderBlocks((string)$item['text'], $readonly)?></div>
				<?php endif; ?>
				<?php if ($mediaCount > 0): ?>
					<div class="kv04-note__media kv04-media-grid kv04-media-grid--<?=$gridCount?><?=$mediaCount > 4 ? ' kv04-media-grid--more' : ''?>">
						<?php foreach ($media as $index => $file): ?>
							<?php
							$isOverflow = $mediaCount > 4 && $index >= 3;
							if ($mediaCount > 4 && $index > 3)
							{
								continue;
							}
							?>
							<?php if (!empty($file['is_image'])): ?>
								<div class="kv04-media-item">
									<button type="button"
										class="kv04-media-thumb<?=$isOverflow ? ' kv04-media-thumb--more' : ''?>"
										data-lightbox="image"
										data-src="<?=htmlspecialcharsbx($file['src'])?>"
										data-file-id="<?=(int)$file['id']?>"
										aria-label="Открыть изображение">
										<img src="<?=htmlspecialcharsbx($file['src'])?>" alt="" loading="lazy" decoding="async">
										<?php if ($isOverflow): ?>
											<span class="kv04-media-thumb__count">+<?=$extraCount?></span>
										<?php endif; ?>
									</button>
									<?php if (!$isOverflow && !$readonly): ?>
										<button type="button"
											class="kv04-media-item__share"
											data-share-media
											aria-label="Поделиться файлом" title="Поделиться">&#8599;</button>
										<button type="button"
											class="kv04-media-item__remove"
											data-media-delete
											data-file-id="<?=(int)$file['id']?>"
											aria-label="Удалить файл">&times;</button>
									<?php endif; ?>
								</div>
							<?php elseif (!empty($file['is_video'])): ?>
								<div class="kv04-media-item">
									<button type="button"
										class="kv04-media-thumb kv04-media-thumb--video<?=$isOverflow ? ' kv04-media-thumb--more' : ''?>"
										data-lightbox="video"
										data-src="<?=htmlspecialcharsbx($file['src'])?>"
										data-file-id="<?=(int)$file['id']?>"
										aria-label="Открыть видео">
										<?php /* #t=0.1 — не украшение: без метки времени Safari на iPhone
										   рисует вместо миниатюры чёрный прямоугольник, потому что кадр
										   декодируется только при воспроизведении. Просмотрщик берёт
										   адрес из data-src, там метки нет. */ ?>
										<video src="<?=htmlspecialcharsbx($file['src'])?>#t=0.1" muted playsinline preload="metadata"></video>
										<span class="kv04-media-thumb__play" aria-hidden="true"></span>
										<?php if ($isOverflow): ?>
											<span class="kv04-media-thumb__count">+<?=$extraCount?></span>
										<?php endif; ?>
									</button>
									<?php if (!$isOverflow && !$readonly): ?>
										<button type="button"
											class="kv04-media-item__share"
											data-share-media
											aria-label="Поделиться файлом" title="Поделиться">&#8599;</button>
										<button type="button"
											class="kv04-media-item__remove"
											data-media-delete
											data-file-id="<?=(int)$file['id']?>"
											aria-label="Удалить файл">&times;</button>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="kv04-note__footer">
					<time><?=htmlspecialcharsbx($item['date'])?></time>
				</div>
				<?php if (!$readonly): ?>
					<button type="button" class="kv04-note__share" data-share-note aria-label="Поделиться заметкой" title="Поделиться">&#8599;</button>
					<button type="button" class="kv04-note__remove" data-delete aria-label="Удалить заметку" title="Удалить заметку">&times;</button>
				<?php endif; ?>
			</article>
			<?php
		}
	}
}
