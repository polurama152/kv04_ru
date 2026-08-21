# Tech stack

- PHP 8.4 on prod + 1C-Bitrix. Custom modules in `public_html/local/modules/kv04.*`.
- Diary UI: custom HTML shell (no eshop template), CSS in `assets/diary-theme.css` + component styles. Highlight.js CDN (`atom-one-dark`).
- DB utf8mb4. Diary uses `main`, `iblock`, `highloadblock`.
- Bitrix API via **bitrix** MCP, not grepping kernel.
- Windows / PowerShell. PhpStorm server `kv04`. SSH `infopolura@77.222.40.47`.
