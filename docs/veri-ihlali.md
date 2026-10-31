# Data breach plan (veri ihlali planı)

What to do if personal data of Jumplearner users may have leaked, been changed or been lost. Turkish law (KVKK) expects the Personal Data Protection Board to be notified **within 72 hours** of learning about a breach; the GDPR has the same deadline for EU users. Keep this short enough to follow under stress.

## Who

| Role | Person | Contact |
|---|---|---|
| Data controller, decides and notifies | Tolga Ayberk Gültekin | info@jumplearner.com |
| Backup | *not set — pick someone* | |

## Where a breach shows up

- Railway and Cloudflare logs (errors, traffic spikes, requests to admin pages)
- Admin panel: audit log (`admin_audit`), activity events, AI usage (`ai_usage`), health page
- AbuseGuard budget e-mails (unexpected Gemini spend can mean a leaked key)
- Unusual usage in the Gemini, Dodo, Mailtrap or Google Cloud dashboards
- A user or researcher writing to info@jumplearner.com

## Hour 0–24: contain

1. Write down the time you learned about it — the 72 hours start here.
2. Rotate whatever may be exposed: Gemini keys, Dodo API key and webhook secret, Mailtrap token, Railway tokens, `DATABASE_URL` password, admin passwords.
3. If accounts may be taken over: delete all sessions and API tokens (`DELETE FROM sessions; DELETE FROM api_tokens;`) so everyone signs in again.
4. Keep evidence before it rotates away: export the relevant Railway/Cloudflare logs and a database snapshot; note what you saw and when.
5. If the cause is a code bug, fix it on a branch and deploy (see `AGENTS.md`).

## Hour 24–48: assess

- Which data: e-mail, name, password hashes, chat messages, learning data, payment identifiers (we never hold card numbers — Dodo and Google Play do).
- How many people, and in which countries (EU users → GDPR too).
- Is it still happening? Is the risk to people high (e.g. readable chats, reused passwords)?

## Before hour 72: notify

1. **KVKK Board:** the data breach notification form on kvkk.gov.tr. If not everything is known yet, notify with what is known and complete it later.
2. **EU supervisory authority** if EU residents are affected (GDPR Art. 33).
3. **Users:** as soon as possible, by e-mail — what happened, which data, what we did, what they should do (change a reused password, watch for phishing).
4. **Processors:** tell the affected provider (Railway, Cloudflare, Google, Dodo, Mailtrap) if the breach is on their side or touches their systems.

## Afterwards

- Record every breach in a breach log (date, what, how many, actions, whether notified), including ones that did not need notifying.
- Find the root cause, fix it, and update this plan.
