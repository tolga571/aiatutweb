# Mobile API v1

Used by the Jumplearner app (private repo `tolga571/jumplearner-mobile`).
Code: `src/Api/Router.php` (routes + handlers), `src/Api/Tokens.php`.

**Keep web and app in step:** features live in the shared classes (`Auth`,
`Chat`, `Flashcard`, `Mistakes`, `Account`, `TokenManager`); the API and the
web pages are thin layers over them. When a web feature changes, change the
API (and the app) in the same piece of work.

**Never break old apps:** add fields and endpoints freely; don't rename or
remove them. A breaking change means `/api/v2`. Raise `MOBILE_MIN_VERSION`
(Railway variable) to make outdated apps ask the user to update.

## Conventions

* Base URL `https://jumplearner.com/api/v1`, JSON in and out.
* Success: `{"ok": true, "data": {...}}`.
  Failure: `{"ok": false, "error": {"code": "...", "message": "...", "fields": {...}?}}`.
  `message` is already in the user's language; branch on `code`.
* HTTP status: 401 `unauthorized` / `invalid_credentials`, 403 `plan_required`
  / `trial_expired` / `quota_exhausted` / `account_suspended` /
  `confirmation_failed`, 404, 405, 422 `validation`, 429 `rate_limited`.
  Unexpected errors are **200** with `server_error` (Cloudflare hides 5xx bodies).
* Headers sent by the app: `Authorization: Bearer jl_...`, `X-App-Lang`,
  `X-App-Version`, `X-App-Platform`.
* Tokens: issued at sign-in, 90-day sliding expiry, revoked on logout,
  password reset and account deletion; a suspended account is rejected.

## Endpoints

| | Path | Auth | Notes |
|---|---|---|---|
| GET | `/config` | – | min app version, languages (published only), form options, feature switches, links |
| GET | `/i18n/{lang}` | – | all UI strings (English fallback, no `admin.*`), `ETag` → 304 |
| POST | `/auth/register` | – | `email, password, name, accept_terms, device?` → `{token, user}` (201) |
| POST | `/auth/login` | – | `email, password, device?` → `{token, user}` |
| POST | `/auth/google` | – | `id_token` (from native Google Sign-In; audience must be `GOOGLE_CLIENT_ID` or one of `GOOGLE_MOBILE_CLIENT_IDS`) |
| POST | `/auth/forgot-password` | – | `email`; always ok |
| POST | `/auth/logout` | user | revokes this token |
| GET | `/me` | user | profile, plan, quota, trial |
| PATCH | `/me` | user | any of `native_lang, target_lang, cefr_level, learning_goal, interest_area, ui_lang, name` |
| POST | `/onboarding` | user | same fields as the web form; also starts the trial |
| POST | `/trial/start` | user | |
| GET | `/dashboard` | plan | user + stats + recent conversations + tip |
| GET | `/topics` | plan | chat scenarios |
| GET | `/conversations` | plan | `?limit=` |
| GET | `/conversations/{id}` | plan | messages (AI ones include corrections, words, segmented…) |
| POST | `/chat` | plan | `message, conversation_id?, topic_id?` → same result as the web chat + `message_id` |
| POST | `/messages/{id}/report` | user | `reason: offensive|harmful|wrong|other, note?` (Play AI policy) → `ai_reports` |
| GET | `/flashcards/stats` | plan | tops up the starter deck like the web page |
| GET | `/flashcards` | plan | `?tab=due|chat|all&category=&q=&level=` |
| GET | `/flashcards/categories` | plan | |
| POST | `/flashcards/review` | plan | `vocab_id, quality (0-5)` |
| GET/POST | `/flashcards/packs` | plan | list / add `level` |
| GET | `/mistakes` | plan | |
| POST | `/mistakes/{id}/review` | plan | `action` (same as web) |
| GET | `/alphabet` | – | `?lang=`; `learned` filled when signed in |
| POST | `/alphabet/toggle` | user | `lang, letter_key` |
| GET | `/account/export` | user | everything we store |
| DELETE | `/account` | user | `password`, or `confirm_email` for Google accounts |

"plan" = an active plan or the free trial (same rule as the website).
