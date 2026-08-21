# Known caveats

- `public_html/bitrix/modules/main.broken.20260627/` — broken `main` snapshot. Leave it. Diary `index.php` skips `epilog_after.php` (custom HTML after `prolog_before.php` only).
- `bitrix/tmp/restore.removed/` — restore leftovers, not source.
- Autoload: `registerAutoLoadClasses('kv04.diary', relative)` without `includeModule` → `/bitrix/modules/`. Site: `load.php` → boot → always `include.php` with `registerAutoLoadClasses(null, /local/ paths)`.
- PHP 8.4: `SetPropertyValuesEx` with integer file IDs fatals (`$val["del"]`). MEDIA write via `NoteService::setMediaProperty()` into `b_iblock_element_prop_m*`.
- `Html::sanitize()`: isolate `<pre>` before `strip_tags`; never run `on\w+=` regex on code text (`<?`, `$sSectionName` get eaten).
- Old notes saved before sanitize fix may contain `KV04PRE0` garbage — manual edit only.
- AJAX «Нет связи» often means HTTP 500 HTML instead of JSON.
- PhpStorm auto-upload Always → local edits can hit live kv04 immediately.
- Mixed `<?` / `<?php` on old public pages; keep the file's existing style.
- Shop IBLOCK_ID 2 is not on the homepage anymore.
