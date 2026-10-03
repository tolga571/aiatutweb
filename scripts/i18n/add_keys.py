#!/usr/bin/env python3
"""Add or update UI strings in lang/*.json.

Input is a JSON file: {"key": {"en": "...", "tr": "...", ...}, ...}.
English is the master list (see src/Language.php); other languages
missing a key fall back to English until translated, e.g. with the
admin "Translate missing with AI" button.

    python3 scripts/i18n/add_keys.py new_keys.json
"""
import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2] / 'lang'


def main(path):
    new = json.loads(pathlib.Path(path).read_text(encoding='utf-8'))
    by_lang = {}
    for key, values in new.items():
        if 'en' not in values:
            sys.exit(f'{key}: an English value is required')
        for lang, value in values.items():
            by_lang.setdefault(lang, {})[key] = value
    for lang, strings in by_lang.items():
        f = ROOT / f'{lang}.json'
        data = json.loads(f.read_text(encoding='utf-8')) if f.exists() else {}
        data.update(strings)
        f.write_text(json.dumps(dict(sorted(data.items())), ensure_ascii=False, indent=4) + '\n', encoding='utf-8')
        print(f'{lang}: {len(strings)} keys written')


if __name__ == '__main__':
    main(sys.argv[1])
