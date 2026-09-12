#!/usr/bin/env python3
"""
Embed demo/art/*.jpg into demo/index.html as data URIs.

The demo is delivered as one file that people open straight from disk, so
images referenced by path or URL are exactly what goes missing. Embedding them
makes the page self-contained: nothing to download, nothing to block.

Swapping in real photographs:
    1. Drop five images into demo/art/, named in the same order
       (01-*.jpg … 05-*.jpg). 16:9 or wider; they are cropped with object-fit.
    2. python3 tools/embed-art.py
"""
import base64, os, re, sys

ROOT = os.path.join(os.path.dirname(__file__), '..')
ART = os.path.join(ROOT, 'demo', 'art')
PAGE = os.path.join(ROOT, 'demo', 'index.html')

files = sorted(f for f in os.listdir(ART) if f.lower().endswith(('.jpg', '.jpeg', '.png', '.webp')))
if not files:
    sys.exit('no images in demo/art/')

html = open(PAGE, encoding='utf-8').read()
slots = list(re.finditer(r'<img\b[^>]*\bdata-photo\b[^>]*>', html))
if len(slots) != len(files):
    sys.exit(f'{len(slots)} image slots in the page but {len(files)} files in demo/art/')

MIME = {'.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp'}
total = 0
out, cursor = [], 0

for slot, name in zip(slots, files):
    path = os.path.join(ART, name)
    raw = open(path, 'rb').read()
    total += len(raw)
    uri = f'data:{MIME[os.path.splitext(name)[1].lower()]};base64,' + base64.b64encode(raw).decode()
    tag = slot.group(0)
    if 'src="' in tag:
        new_tag = re.sub(r'src="[^"]*"', lambda m: 'src="' + uri + '"', tag, count=1)
    else:
        new_tag = tag[:-1] + f' src="{uri}">'
    out.append(html[cursor:slot.start()])
    out.append(new_tag)
    cursor = slot.end()
    print(f'  {name:22} {len(raw)/1024:6.1f} KB embedded')

out.append(html[cursor:])
open(PAGE, 'w', encoding='utf-8').write(''.join(out))
print(f'embedded {len(files)} images ({total/1024:.0f} KB source) into demo/index.html '
      f'-> {os.path.getsize(PAGE)/1024:.0f} KB')
