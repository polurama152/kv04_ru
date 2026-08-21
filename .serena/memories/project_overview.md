# kv04_ru — overview

Local copy of **kv04.ru**. CMS: 1C-Bitrix. Document root: `public_html/`.

**Product:** private PIN-diary on the homepage (Telegram-like notes, no Bitrix `$USER`). Goal: partner modules `kv04.*` on Bitrix Marketplace.

- Project root: `D:\kv04_ru`. Do **not** edit `D:\EDT_dev`.
- Onboarding: `docs/ONBOARDING.md`. Rules: `.cursor/rules/`. Specs: `docs/specs/`.
- PhpStorm auto-upload to remote `kv04` (`autoUpload=Always`). SSH: `infopolura@77.222.40.47` → `/home/i/infopolura/kv04_ru/public_html/`.
- No git at project root.
- Kernel `public_html/bitrix/**` is not project source; use Bitrix MCP for API.
- Do **not** update specs or Serena memories unless the user explicitly asks.
