# Changelog

Versions of jumplearner.com (web + API). Each one is a git tag on the `master` commit that went live; see "Versions and rollback" in `AGENTS.md`.

## v1.3.1 — 2026-10-31

- **No sales to Türkiye:** visitors Cloudflare places in Türkiye see a notice on the pricing page and the buy buttons are off; a direct checkout link is refused. If a subscription billed to Türkiye still goes through, the Dodo webhook cancels it at once, refunds it in full and e-mails the owner and the customer (`SALES_BLOCKED_COUNTRIES`, default `TR`). The free trial and the stories stay open.
- **Terms:** a "Purchases and Payments" section — Dodo Payments is the merchant of record (link to its Buyer Terms), renewal and cancellation, our refund policy on top, and no sales to consumers in Türkiye. The pricing page links the Buyer Terms too.

## v1.3 — 2026-10-27

- **Stories read aloud with natural voices:** every story has a recorded reading in ten languages (Microsoft Edge neural voices), with the sentence marked while it plays and each word playable on its own in the word drawer. The browser's voice is only a fallback now — and for Armenian, which has no free voice we could verify (Gemini's TTS read the wrong sentences).
- **More stories:** 11 stories in all 11 languages (was 3).
- **Search engines:** the sitemap now lists every story in every language, so Google can find them.
- **Sign-up consent:** the sign-up box is now for the Terms of Service only, with the Privacy Policy as a separate notice; signing up with Google shows that continuing accepts the terms. When and which version of the terms each new account accepted is stored.
- **Pricing:** a line under the plans says subscriptions renew automatically and can be cancelled anytime on that page, with links to the terms and refund policy.
- **Privacy Policy:** new sections on the data controller, international transfers and your rights under KVKK and the GDPR (all 11 languages).
- **No third-party files on public pages:** fonts (Inter, Hanken Grotesk, Geist, Material Symbols), Tailwind, GSAP and Lenis are served from jumplearner.com instead of Google Fonts and CDNs; the admin panel's CDN files are pinned with integrity hashes.
- **Data breach plan:** `docs/veri-ihlali.md`.

## v1.2.6 — 2026-10-26

- **Mobile landscape video sizing:** refined mobile landscape video styling (`width: auto`, `height: 100dvh`, `aspect-ratio: 16/9`) to ensure exact 16:9 geometric aspect ratio and full screen height on all smartphone viewports.
- **Cache buster bump:** bumped frontend CSS, JS, and media version strings to `?v=6`.

## v1.2.5 — 2026-10-26

- **Mobile video experience & aspect ratio:** corrected `.hm-video` aspect ratio from `16/10` to `16/9` to prevent bottom-edge cropping of input bars and quotas.
- **Mobile immersive lightbox player:** transformed `.hm-lightbox` into an edge-to-edge full-viewport player on mobile devices with floating header overlay, rotation tip button, and seamless fullscreen support.
- **Mobile landscape full-screen:** smartphones held horizontally now fill 100% of the display with zero letterboxing or margin waste.
- **Video composition zoom & clarity:** upgraded video composition engine (`compose.py`) with `ZMAX = 2.0`, `SCALE_MULT = 3` (3840x2160 lanczos resampling) and refined focus rectangles across all 9 feature videos, increasing in-video text size by up to 2x for crystal-clear readability on phones.
- **Cache buster bump:** bumped all media and frontend cache strings to `?v=5`.

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
