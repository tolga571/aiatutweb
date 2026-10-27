#!/usr/bin/env python3
"""Recorded read-aloud for the stories: one MP3 per story and language
(public/media/stories/<slug>/<lang>.mp3) plus its timings (<lang>.json):
when each sentence starts and ends, and each word for the word drawer.

Voices: Microsoft Edge's neural voices (edge-tts) for ten languages — they
also give word timings. Armenian has no Edge voice and no free TTS we
could verify: Gemini's TTS read other sentences than the ones it was given
(checked 2026-10-25 by transcribing the audio back), so Armenian is skipped
and the reader falls back to the browser's voice. Never ship audio for a
language without checking a transcript of it against the text.

    ~/.cache/jl-staging/ttsvenv/bin/python scripts/stories/audio.py [slug ...] [--force]

Needs ffmpeg.
"""
import asyncio, json, pathlib, re, subprocess, sys, tempfile, unicodedata

import edge_tts

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'public' / 'media' / 'stories'
VOICES = {
    'en': 'en-US-AriaNeural', 'de': 'de-DE-KatjaNeural', 'fr': 'fr-FR-DeniseNeural', 'es': 'es-MX-DaliaNeural',
    'ar': 'ar-SA-ZariyahNeural', 'ja': 'ja-JP-NanamiNeural', 'zh': 'zh-CN-XiaoxiaoNeural', 'ru': 'ru-RU-SvetlanaNeural',
    'el': 'el-GR-AthinaNeural', 'hi': 'hi-IN-SwaraNeural',
}
GAP = 0.45          # silence between sentences, seconds
RATE = '-10%'       # a little slower than normal: these are learners


def duration(path):
    return float(subprocess.check_output(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', str(path)]).decode().strip())


def norm(s):
    return unicodedata.normalize('NFC', s)


# A second voice per language, used when the first keeps refusing a sentence
# (the Greek service sometimes answers "no audio" for a while).
ALT_VOICES = {'el': 'el-GR-NestorasNeural', 'ar': 'ar-SA-HamedNeural', 'hi': 'hi-IN-MadhurNeural'}


async def edge_sentence(text, voice, path):
    """MP3 of one sentence + word boundaries [(text, start, end)] in seconds."""
    lang = voice[:2]
    voices = [voice] * 4 + ([ALT_VOICES[lang]] * 3 if lang in ALT_VOICES else [])
    for attempt, v in enumerate(voices):
        try:
            words, audio = [], bytearray()
            c = edge_tts.Communicate(text, v, rate=RATE, boundary='WordBoundary')
            async for ch in c.stream():
                if ch['type'] == 'audio':
                    audio += ch['data']
                elif ch['type'] == 'WordBoundary':
                    s = ch['offset'] / 1e7
                    words.append((ch['text'], s, s + ch['duration'] / 1e7))
            path.write_bytes(bytes(audio))
            return words
        except Exception:
            if attempt == len(voices) - 1:
                raise
            await asyncio.sleep(3 + attempt * 4)


def align(sentence, tokens, words, offset):
    """Times for our tokens from the TTS word boundaries, by character position."""
    spans, pos = [], 0
    unspaced = not re.search(r'\s', sentence) and len(tokens) > 1
    for t in tokens:
        i = sentence.find(t['t'], pos)
        if i < 0:
            spans.append(None)
            continue
        spans.append((i, i + len(t['t'])))
        pos = i + len(t['t'])
    bounds, pos = [], 0
    for text, s, e in words:
        i = sentence.find(norm(text), pos)
        if i < 0:
            continue
        bounds.append((i, i + len(norm(text)), s, e))
        pos = i + len(norm(text))
    out = {}
    for ti, sp in enumerate(spans):
        if not sp:
            continue
        hit = [(s, e) for a, b, s, e in bounds if a < sp[1] and b > sp[0]]
        if hit:
            out[ti] = [round(offset + min(h[0] for h in hit), 3), round(offset + max(h[1] for h in hit), 3)]
    return out


async def build(slug, lang, text, force):
    out_mp3, out_json = OUT / slug / f'{lang}.mp3', OUT / slug / f'{lang}.json'
    if out_json.exists() and not force:
        return 'skip'
    out_mp3.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        tmp = pathlib.Path(tmp)
        # Pieces are joined as WAV and encoded once, so MP3 padding can't shift the timings.
        silence = tmp / 'gap.wav'
        subprocess.run(['ffmpeg', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i', 'anullsrc=r=24000:cl=mono', '-t', str(GAP), '-c:a', 'pcm_s16le', str(silence)], check=True)
        parts, timing, t = [], {'v': 1, 'sentences': [], 'words': {}}, 0.0
        for si, s in enumerate(text['sentences']):
            raw = tmp / f's{si}.mp3'
            words = await edge_sentence(norm(s['text']), VOICES[lang], raw)
            # Same format for every piece, so the joined file has exact timings.
            fixed = tmp / f'f{si}.wav'
            subprocess.run(['ffmpeg', '-loglevel', 'error', '-y', '-i', str(raw), '-ar', '24000', '-ac', '1', '-c:a', 'pcm_s16le', str(fixed)], check=True)
            d = duration(fixed)
            timing['sentences'].append([round(t, 3), round(t + d, 3)])
            for ti, span in align(norm(s['text']), s['tokens'], words, t).items():
                timing['words'][f'{si}.{ti}'] = span
            parts += [fixed, silence]
            t += d + GAP
        lst = tmp / 'list.txt'
        lst.write_text(''.join(f"file '{p}'\n" for p in parts[:-1]))
        subprocess.run(['ffmpeg', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', str(lst), '-ac', '1', '-c:a', 'libmp3lame', '-b:a', '40k', str(out_mp3)], check=True)
    out_json.write_text(json.dumps(timing, separators=(',', ':')) + '\n')
    words = sum(len(s['tokens']) for s in text['sentences'])
    return f"{duration(out_mp3):.0f}s, {out_mp3.stat().st_size // 1024} KB, word times {len(timing['words'])}/{words}"


async def main():
    args = [a for a in sys.argv[1:] if a != '--force']
    force = '--force' in sys.argv
    for f in sorted((ROOT / 'data' / 'stories').glob('*.json')):
        if f.name == 'sources.json':
            continue
        story = json.loads(f.read_text())
        if args and story['slug'] not in args:
            continue
        for lang, text in story['texts'].items():
            if lang not in VOICES:
                continue
            try:
                print(story['slug'], lang, await build(story['slug'], lang, text, force), flush=True)
            except Exception as e:
                print(story['slug'], lang, 'FAILED', str(e)[:300], flush=True)

asyncio.run(main())
