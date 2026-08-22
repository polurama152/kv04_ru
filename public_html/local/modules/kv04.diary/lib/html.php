<?php

namespace Kv04\Diary;

class Html
{
	private const CODE_BLOCK_TAGS = ['pre', 'code', 'span'];

	public static function sanitize(string $html): string
	{
		$codeBlocks = [];
		$html = preg_replace_callback('#<pre\b[^>]*>.*?</pre>#is', static function (array $match) use (&$codeBlocks): string {
			$key = '[[KV04PRE:' . count($codeBlocks) . ']]';
			$codeBlocks[$key] = self::sanitizeCodeBlock($match[0]);
			return $key;
		}, $html) ?? $html;

		$html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
		$html = preg_replace('#on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';

		// <a> из белого списка убран намеренно. Ссылки в заметке не хранятся
		// разметкой: адрес лежит обычным текстом, а кликабельным его делает
		// показ. Значит опасной ссылке в базе взяться неоткуда, и вырезать
		// строку javascript: из текста больше не нужно — раньше она пропадала
		// и из обычной прозы, стоило написать о ней в заметке.
		$html = strip_tags($html, '<p><br><pre><code><img><video><source><span><div>');

		foreach ($codeBlocks as $key => $block)
		{
			$html = str_replace($key, $block, $html);
		}

		return trim($html);
	}

	private static function sanitizeCodeBlock(string $block): string
	{
		$block = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $block) ?? $block;

		return preg_replace_callback(
			'#<(/?)([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>#',
			static function (array $match): string {
				$tag = strtolower($match[2]);
				if (!in_array($tag, self::CODE_BLOCK_TAGS, true))
				{
					return '';
				}

				$attrs = self::sanitizeCodeBlockAttrs($match[3]);

				return '<' . $match[1] . $tag . $attrs . '>';
			},
			$block
		) ?? $block;
	}

	private static function sanitizeCodeBlockAttrs(string $attrs): string
	{
		if (!preg_match('#\bclass=(["\'])(.*?)\1#i', $attrs, $match))
		{
			return '';
		}

		$class = preg_replace('#[^a-zA-Z0-9_\s-]#', '', $match[2]) ?? '';
		if ($class === '')
		{
			return '';
		}

		return ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
	}

	public static function excerpt(string $html, int $length = 40): string
	{
		$text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
		if ($text === '')
		{
			return 'Заметка';
		}
		if (mb_strlen($text) <= $length)
		{
			return $text;
		}
		return mb_substr($text, 0, $length) . '…';
	}
}
