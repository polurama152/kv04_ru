# Code style

- Custom PHP in `local/`: `<?php`, UTF-8, D7 namespaces `Kv04\...`, Bitrix APIs.
- Public pages may mix short tags; keep the file's existing style.
- Components: thin `class.php`, domain in module `lib/`. POST + `check_bitrix_sessid()`.
- Do not invent Bitrix signatures — Bitrix MCP.
- Do not edit kernel. Do not print DB credentials.
- Specs and Serena memories: only when the user explicitly asks.
