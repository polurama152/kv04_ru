# kv04.diary — module map

**Load:** `load.php` → `kv04DiaryLoadModule()` → `boot.php` → always `include.php`.

**Classes (`lib/`):**
- `Installer` — HL `Kv04DiaryKey` / `kv04_diary_keys`, iblock type `kv04` code `diary`, options pepper/hlblock_id/iblock_id
- `Auth` — cookie `KV04_DIARY`, session 10h, HMAC
- `PinService` — 4-digit PIN, HMAC-SHA256+pepper, 3 fails → 24h lock (PIN row + IP Option `ipfail_*`)
- `NoteService` — list/add/update/delete/attach/detachMedia; `setMediaProperty()` for PHP 8.4
- `Html` — sanitize; isolate `<pre>` (`sanitizeCodeBlock`)

**Components:** `kv04:diary.pin`, `kv04:diary.feed` under `/local/components/kv04/`.

**Feed POST + sessid:** `add` (text + media[]), `edit` (id, text), `delete` (id), `attach` (id + media[]), `detach_media` (id, file_id), `logout`.
**Pin POST:** `login`, `create`, `logout`.

**Limits:** images jpg/png/gif/webp 8MB; video mp4/webm 20MB. Iblock guests GROUP_ID 2 = W.
