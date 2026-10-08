# End-to-end tests (Playwright)

Browser tests against the **local staging** site (see `AGENTS.md` → Local staging). They register their own throw-away users (`*@example.test`) and check the database with `psql`.

| Script | What it covers | Checks |
|---|---|---|
| `sweep.js <adminSid> <outDir> [sections]` | Whole site. Sections: `guest` (15 pages × 11 languages × desktop/phone), `user` (register → onboarding → chat → flashcards → mistakes → logout/login), `i18n` (logged-in UI in all 11 languages), `admin` (every admin page), `api` (mobile API incl. other-user/IDOR checks) | 424 |
| `lists_test.js <outDir> [phone]` | Card lists (playlists) + study mode, web | 17 |
| `words_test.js <outDir>` | Word bank page + API, 5 UI languages | 16 |
| `api_lists_test.sh` | Card-list API incl. other-user (IDOR) checks | 22 |
| `mobile_lists_test.js <outDir>` | Mobile app (Expo web on :8083) lists + study screen | 9 |
| `mobile_words_test.js` | Mobile app word bank, Russian UI | 4 |
| `mobile_parity_test.js` | Mobile app ↔ website: sign-up + resend, Home stats, word bank (category, example, bulk add), chat study session, mistakes stats, Profile pages + data export | 9 |
| `prodcheck.js` | Read-only smoke test of **production** guest pages (no sign-ups) | 110 |

## Running

```bash
npm i -D playwright                                # or point PLAYWRIGHT_MODULE at an existing install
export CHROME_PATH=/usr/bin/google-chrome          # optional: use the system Chrome
export PSQL_ARGS="-h /tmp/jlpg -p 5433 -d aitut"   # how to reach the staging DB (this is the default)

# Before each run: the abuse limits (sign-ups and trials per IP) apply locally too.
psql $PSQL_ARGS -c "DELETE FROM login_attempts; DELETE FROM trial_grants;"

# The admin section of sweep.js needs an admin session row (admin id 2 = the local admin):
SID=sweepadm$(date +%s)
psql $PSQL_ARGS -c "INSERT INTO sessions(id,data,expires_at) VALUES ('$SID','admin_id|i:2;admin_role|s:5:\"admin\";admin_last_seen|i:$(date +%s);', now()+interval '1 day')"
node tests/e2e/sweep.js $SID /tmp user,i18n,admin,api
node tests/e2e/sweep.js x /tmp guest                                   # run sections separately; one long run once got OOM-killed
BASE=https://jumplearner.com/ node tests/e2e/sweep.js x /tmp guest     # production, guest pages only (no DB access)
```

Known noise on production: a few "Provider's accounts list is empty" console errors come from Google One Tap in a browser with no Google account signed in — not a site bug.

The mobile tests need the app's web preview: in `jumplearner-mobile`, `CI=1 EXPO_PUBLIC_API_URL=http://127.0.0.1:8090/api/v1 npx expo start --web --port 8083`. Restart it after editing the app — the dev server sometimes keeps serving stale code.
