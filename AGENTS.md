# Jumplearner — notes for coding agents (Claude Code, Gemini / Antigravity, …)

AI language tutor at https://jumplearner.com. This repo is the **website plus the JSON API** the mobile app uses. The app is a separate repo, `jumplearner-mobile` (Expo / React Native) — see its `AGENTS.md`. Two agents work on this project in parallel; this file is the shared ground truth.

**This repository is public.** Never commit `.env`, API keys, tokens, real user data or personal e-mail addresses.

## Hard rules

1. **Pushing to `master` deploys to production** (Railway builds `master` automatically, in about a minute). Push to `master` only when the project owner has approved that change. Work on a feature branch; before a deploy tag the current `master` (`git tag pre-<feature> origin/master`) so it can be rolled back.
2. **Never return a 5xx with a JSON body.** Cloudflare sits in front and replaces 5xx bodies with its own HTML, so the site/app can't read the error. JSON endpoints answer 200/4xx with `{"success":false,…}` (web) or `{"ok":false,"error":{…}}` (API).
3. **Database changes must survive the upgrade from the live release.** `Database::initialize()` runs on every request and creates/migrates the schema, so a fatal error there blanks every page (this happened on 2026-10-04). Use `CREATE TABLE/INDEX IF NOT EXISTS` and `ADD COLUMN IF NOT EXISTS`. Put one-off data migrations at the end of `migrate()`, inside a transaction, guarded by an `app_state` key, in a `try/catch` that logs and carries on. Before deploying, build a database with the **live** code (`git archive origin/master`) and run the new code on it.
4. **Web and mobile ship together.** Every user-facing feature lands on the website, in the API and in the app. Exceptions are decided by the owner.
5. **Static asset cache:** Cloudflare caches CSS/JS by full URL (`?v=N`). Bump `?v=` in the same commit as the file change, and don't request the new `?v=` URL before that deploy is live — the old file would get cached under the new key.
6. **Parallel work:** `git fetch` and check `origin/master` before you start. Don't edit another agent's working copy (`.claude/worktrees/*` belong to Claude Code). Don't work on the same feature as the other agent at the same time — the owner splits the work.

## Coordination board (two agents)

Outside the repos, at `/home/kali/jumplearner-agents/` (local only, never committed): `PANO.md` is the task board (who has which task, branch, files), `MESAJLAR.md` holds messages between agents, and `README.md` has the rules. **At the start of every task:** read both files and `git fetch`. **Before touching code:** claim the task and list the files you'll change. **When done:** update the status and leave a message if the other agent needs to know something. Only take tasks the owner put on the board.

## Stack and layout

- Plain PHP 8 (no framework), PostgreSQL, Composer (`vendor/`), Tailwind via CDN, vanilla JS. Router: `public/index.php` (`?page=…` switch). Views: `views/*.php`. Classes: `src/` (PSR-4 `App\Src\`).
- Mobile API: `/api/v1/*` → `src/Api/Router.php` (bearer tokens in `api_tokens`). The route table is at the top of `dispatch()`; access levels `public` / `user` / `plan`.
- AI: Google Gemini (`src/GeminiClient.php`), cost per call in `ai_usage`. Spend cap and abuse limits: `src/AbuseGuard.php` (`AI_MONTHLY_BUDGET_USD`). Locally set `GEMINI_FAKE=1` for a canned reply — don't spend the production key on tests.
- Payments: Dodo is live; Paddle/FastSpring code exists (sandbox). Admin panel: `?page=admin-*` (`src/Admin*.php`, `views/admin/`), English UI.
- Security basics in place, keep them: CSRF on every POST (`csrf_verify`); ownership checks on every id that comes from the client (cards, lists, conversations); `htmlspecialchars` / JSON-encoded data in views.

## Languages (i18n)

- 11 languages for both interface and learning: en, de, fr, es, ar (right-to-left), ja, zh, ru, el, hi, hy. **Turkish was removed completely** (2026-10-04/05, including user data) — don't add it back.
- Registry: `languages` table + `src/Language.php`. UI strings: `lang/<code>.json` (keys like `fc.study`); `__('key')` / `t('key', ['n' => 3])` in PHP with `{name}` placeholders. Admin overrides live in `ui_translations`. **Every new key goes into all 11 files.**
- Inside `<script>` blocks print translations with `jsq(__('key'))` or `json_encode` — a raw apostrophe ("l'activation") breaks the whole script. `<html dir>` comes from `Language::dir()`.
- Users have `native_lang` (meanings, explanations and by default the interface), `ui_lang` (interface override) and `target_lang` (the language being learned; it can never equal the native language).

## Flashcards (main feature area)

- Cards: `vocabulary_words` (per user and language) + `user_flashcards` (SM-2 review state) — `src/Flashcard.php`.
- Lists ("playlists"): `card_lists` / `card_list_items` — `src/CardLists.php`. Every language has a default "Saved" list; a card can be in several lists.
- Study mode: one card at a time, swipe for the next, tap to flip. Passing a card without flipping = Good, flipping = Again.
- Word bank: `?page=words`, `GET|POST /api/v1/words` — the starter deck (`data/flashcards_data.php`) plus packs (`data/vocab/<lang>.json`, built by `scripts/vocab/`).

## Local staging (no production data needed)

```bash
# Postgres 18 on 127.0.0.1:5433 (data directory anywhere, e.g. ~/.cache/jl-staging/pgdata)
/usr/lib/postgresql/18/bin/initdb -D <dir>/pgdata -U <you> --auth=trust     # once
/usr/lib/postgresql/18/bin/pg_ctl -D <dir>/pgdata -o "-p 5433 -k /tmp/jlpg -c listen_addresses=127.0.0.1" start
createdb -h 127.0.0.1 -p 5433 aitut                                           # once
# .env: DATABASE_URL=postgresql://<you>@127.0.0.1:5433/aitut and GEMINI_FAKE=1, then
php -S 127.0.0.1:8090 -t public          # the app creates its schema on the first request
```

The app's web preview runs on :8083 against this API. These ports are shared between agents — don't start a second server on them.

## Before you hand work over

- `php -l` every changed PHP file; `node --check` changed JS.
- Run the matching suite in `tests/e2e/` plus `sweep.js` (see its README). Everything passes today (418/418 + the feature suites).
- After a deploy: wait until production serves the new code, then smoke-test it (`tests/e2e/prodcheck.js`, `BASE=https://jumplearner.com/ node tests/e2e/sweep.js x /tmp guest`).

## Commit style

Conventional prefixes (`feat(scope):`, `fix(scope):`, `chore(…):`), a short subject, and a body that says **why**. Match the surrounding code: comment density, naming, idioms.
