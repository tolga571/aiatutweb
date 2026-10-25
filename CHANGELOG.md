# Changelog

Versions of jumplearner.com (web + API). Each one is a git tag on the `master` commit that went live; see "Versions and rollback" in `AGENTS.md`.

## v1.3 — 2026-10-25

- **Stories read aloud with natural voices:** every story has a recorded reading in ten languages (Microsoft Edge neural voices), with the sentence marked while it plays and each word playable on its own in the word drawer. The browser's voice is only a fallback now — and for Armenian, which has no free voice we could verify (Gemini's TTS read the wrong sentences).
- **More stories:** the remaining starter stories in all 11 languages.
- **Flashcards page:** no footer any more (it covered the sidebar, including My lists and Word packs); the screen fits without scrolling again.
- **Homepage recordings:** re-recorded on the current site (lists: create a deck, add a word, rename it; stories: listen + word drawer); every clip opens in a larger player with controls and full screen.

## v1.2 — 2026-10-25

- **Stories:** short stories in all 11 languages at `/stories`. Read the plain text or go word by word: every word shows how it is read and what it means in the learner's own language (switchable). Tap a word to hear it (the browser's own voices) and save it to your cards; listen to the whole story sentence by sentence. Everyone can read; saving needs a free account. Admin: write a story in English, build all languages, publish.
- **Homepage:** new homepage with screen recordings of the real site for every part (chat, cards & study, word bank, lists, my mistakes, alphabet, stories), a word-by-word line from a real story, prices and FAQ.
- **Footer:** Learn / Company / Legal columns with every page; the navbar is unchanged except a Stories entry in the Pages menu.
- **API:** `GET /api/v1/stories`, `GET /api/v1/stories/{slug}`, `POST /api/v1/stories/{slug}/words` for the app.

## v1.1 — 2026-10-25

Production before the stories and homepage work (`56a0986`): logo, admin panel v2, Dodo revenue checks, per-request schema check removed. Rollback baseline.

## v1.0 — 2026-09-26

First frozen production release (`36fb3c3`): Dodo payments live, motion layer, security fixes.
