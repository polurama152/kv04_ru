# Suggested commands (Windows)

No `npm test` / phpunit / composer at project root.

```powershell
php -l D:\kv04_ru\public_html\index.php
curl -sS -o NUL -w "%{http_code}`n" https://kv04.ru/
scp "D:\kv04_ru\public_html\local\modules\kv04.diary\lib\noteservice.php" infopolura@77.222.40.47:/home/i/infopolura/kv04_ru/public_html/local/modules/kv04.diary/lib/
```

Prefer Serena search over recursive `dir` of `bitrix/`.
No `.git` unless the user initializes a repo.
PhpStorm auto-upload to `kv04` is ON — experimental edits can hit production.
