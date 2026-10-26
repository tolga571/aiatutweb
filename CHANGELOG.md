# Changelog

Versions of jumplearner.com (web + API). Each one is a git tag on the `master` commit that went live; see "Versions and rollback" in `AGENTS.md`.

## v1.2.4 — 2026-10-26

- **Chat video flag icons:** re-recorded and composed Chat feature video with all self-hosted language flags rendering cleanly in the dropdown selector.
- **Lists video footer fix:** re-recorded and composed Lists feature video with clean background cards and no site footer overflow behind modal dialogs.
- **Cache buster bump:** bumped marketing video and asset cache strings to `?v=4`.

## v1.2.3 — 2026-10-26

- **Google Flow Leo videos & interactive player:** all 9 feature videos (Chat, Cards, Word bank, Lists, Mistakes, Alphabet, Stories, Grammar cards, Practice) narrated by Leo are now integrated. Every video preview on the homepage is clickable and opens in a larger full-screen player with audio narration and native controls.
- **Grammar & Practice tabs:** added Grammar cards and Practice quiz feature walkthroughs to the homepage tour with preview loops and full narrated videos.

## v1.2.2 — 2026-10-26

- **Brand header:** hero section now highlights the Jumplearner brand typography directly underneath the emblem.
- **Language flags:** self-hosted SVG flags for all 11 languages so dropdowns in chat and menus reliably render flags without external dependency on flagcdn.

## v1.2.1 — 2026-10-25

- **Footer only on content pages:** the learning tools — flashcards, word bank, my mistakes, alphabet — no longer have the site's link footer under them (it took more room than some of the tools; the cookie banner stays).
- **Pricing:** now has the footer, with the legal links (terms, refunds, privacy) every other content page has.
- **Footer on phones:** about 40% shorter — the links of each group sit side by side instead of one per line, and the tagline is left to the homepage. Desktop is unchanged.

## v1.2 — 2026-10-25

- **Stories:** short stories in all 11 languages at `/stories`. Read the plain text or go word by word: every word shows how it is read and what it means in the learner's own language (switchable). Tap a word to hear it (the browser's own voices) and save it to your cards; listen to the whole story sentence by sentence. Everyone can read; saving needs a free account. Admin: write a story in English, build all languages, publish.
- **Homepage:** new homepage with screen recordings of the real site for every part (chat, cards & study, word bank, lists, my mistakes, alphabet, stories), a word-by-word line from a real story, prices and FAQ.
- **Footer:** Learn / Company / Legal columns with every page; the navbar is unchanged except a Stories entry in the Pages menu.
- **API:** `GET /api/v1/stories`, `GET /api/v1/stories/{slug}`, `POST /api/v1/stories/{slug}/words` for the app.

## v1.1 — 2026-10-25

Production before the stories and homepage work (`56a0986`): logo, admin panel v2, Dodo revenue checks, per-request schema check removed. Rollback baseline.

## v1.0 — 2026-09-26

First frozen production release (`36fb3c3`): Dodo payments live, motion layer, security fixes.
