#!/usr/bin/env python3
"""Baut Handbuch und Aushang (HTML → PDF über Chrome); Seitenzahlen im Inhaltsverzeichnis in zwei Durchläufen.

Aufruf:  python3 build.py [--bogen]
  --bogen   zusätzlich Kontrollbögen (je 6 Seiten als PNG) in build/bogen-XX.png

Umgebung: HB_IMG (Screenshots, Standard img/), HB_OUT (Ausgabe, Standard build/), CHROME (Pfad zu Chrome),
          HB_MASK_URL (Adresse der Handy-Pflegemaske für den QR-Code auf dem Aushang).
Benötigt: python3 + Pillow, node (werkzeuge/: npm install), pngquant, poppler-utils (pdfinfo, pdftotext, pdftoppm).
"""
import glob, html, os, re, shutil, subprocess, sys
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, 'src')
IMG = os.environ.get('HB_IMG', os.path.join(HERE, 'img'))
OUT = os.environ.get('HB_OUT', os.path.join(HERE, 'build'))
PLUG = os.path.abspath(os.path.join(HERE, '..', '..'))
TOOLS = os.path.join(HERE, 'werkzeuge')
MASK_URL = os.environ.get('HB_MASK_URL', 'http://intranet-marberger.oetztal.holz-marberger.at/?mm_pflege=1')


def run(cmd, **kw):
    return subprocess.run(cmd, check=True, **kw)


def pdf(src, dst):
    run(['node', os.path.join(TOOLS, 'pdf.js'), src, dst])


# ---- Bilder
os.makedirs(OUT + '/img', exist_ok=True)
for f in glob.glob(IMG + '/*.png'):
    shutil.copy(f, OUT + '/img/')
shutil.copy(PLUG + '/assets/img/marberger-logo.png', OUT + '/img/marberger-logo.png')
for n in ['m-new', 'm-placeholder', 'm-saved']:  # Handy-Aufnahmen für den Aushang oben beschneiden
    im = Image.open(f'{IMG}/{n}.png')
    im.crop((0, 0, im.width, min(im.height, 1150))).save(f'{OUT}/img/{n}-crop.png')
subprocess.run('pngquant --force --skip-if-larger --quality=70-95 --ext .png ' + OUT + '/img/*.png', shell=True)

parts = [open(f'{SRC}/{p}', encoding='utf-8').read() for p in sorted(os.listdir(SRC)) if re.match(r'\d\d-.*\.html$', p)]
doc = '\n'.join(parts)

# ---- Symboltabellen direkt aus dem Plugin (Player-SVGs, Bezeichnungen aus Editorial.php)
js = open(PLUG + '/assets/player/player.js', encoding='utf-8').read()


def icons(var):
    block = re.search(var + r' = \{(.*?)\n  \};', js, re.S).group(1)
    return dict(re.findall(r"(\w+): '([^']*)'", block))


php = open(PLUG + '/includes/WP/Editorial.php', encoding='utf-8').read()
labels = dict(re.findall(r"'(\w+)' => '([^']+)'", re.search(r'ICONS = \[(.*?)\];', php, re.S).group(1)))
nlabels = dict(re.findall(r"'(\w+)' => '([^']+)'", re.search(r'NOTICE_ICONS = \[(.*?)\];', php, re.S).group(1)))


def icon_table(d, labels, fg, bg):
    rows = ''.join(f'<tr><td style="width:13mm"><span style="display:inline-flex;width:10mm;height:10mm;border-radius:50%;background:{bg};align-items:center;justify-content:center">'
                   f'<svg viewBox="0 0 64 64" width="6.2mm" height="6.2mm" fill="none" stroke="{fg}" stroke-width="{4 if fg != "#fff" else 6}" stroke-linejoin="round" stroke-linecap="round">{svg}</svg></span></td>'
                   f'<td>{html.escape(labels.get(k, k))}</td></tr>' for k, svg in d.items())
    return f'<table class="compact"><tbody>{rows}</tbody></table>'


doc = doc.replace('{{ICONS_TILES}}', icon_table(icons('NODE_ICONS'), labels, '#006729', '#e3f0e7'))
doc = doc.replace('{{ICONS_NOTICE}}', icon_table(icons('BANNER_ICONS'), nlabels, '#fff', '#b86e00'))

# ---- Aushang mit QR-Code
qr = run(['node', os.path.join(TOOLS, 'qr.js'), MASK_URL], capture_output=True, text=True).stdout
aushang = open(f'{SRC}/aushang.html', encoding='utf-8').read().replace('{{QR}}', qr)
doc = doc.replace('{{AUSHANG}}', aushang)

# ---- Inhaltsverzeichnis: 1. Durchlauf mit unsichtbaren Markern, Seitenzahlen per pdftotext, 2. Durchlauf
heads = re.findall(r'<h([12]) id="([^"]+)">(.*?)</h\1>', doc)
heads = [(int(l), i, re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', t))).strip()) for l, i, t in heads]
heads.append((1, 'aushang', 'Kurzanleitung „Gruß vom Handy“ (zum Aushängen)'))


def toc(pages):
    items = ''.join(f'<li class="l{l}"><a href="#{i}">{html.escape(t)}</a><span class="dots"></span><span class="pg">{pages.get(i, "")}</span></li>' for l, i, t in heads)
    return f'<ol>{items}</ol>'


def build(pages, markers):
    d = doc.replace('{{TOC}}', toc(pages))
    if markers:
        for _, i, _ in heads:
            d = re.sub(r'(<h[12] id="%s">)' % re.escape(i), r'\1<span style="position:absolute;font-size:2px;color:#fff">@@%s@@</span>' % i, d, count=1)
        d = d.replace('<section class="aushang" id="aushang">', '<section class="aushang" id="aushang"><span style="position:absolute;font-size:2px;color:#fff">@@aushang@@</span>')
    open(OUT + '/handbuch.html', 'w', encoding='utf-8').write(d)
    pdf(OUT + '/handbuch.html', OUT + '/handbuch.pdf')


build({i: '00' for _, i, _ in heads}, True)
n = int(re.search(r'Pages:\s+(\d+)', run(['pdfinfo', OUT + '/handbuch.pdf'], capture_output=True, text=True).stdout).group(1))
pages = {}
for p in range(1, n + 1):
    t = run(['pdftotext', '-f', str(p), '-l', str(p), OUT + '/handbuch.pdf', '-'], capture_output=True, text=True).stdout
    for m in re.findall(r'@@([\w-]+)@@', t):
        pages.setdefault(m, p)
missing = [i for _, i, _ in heads if i not in pages]
print('Seiten:', n, '| ohne Seitenzahl:', missing)
build(pages, False)

# ---- Aushang als eigene Datei
head = open(f'{SRC}/00-head.html', encoding='utf-8').read()
single = head + '<section class="aushang" style="break-before:auto">' + aushang + '</section></body></html>'
open(OUT + '/aushang.html', 'w', encoding='utf-8').write(single)
pdf(OUT + '/aushang.html', OUT + '/aushang.pdf')

# ---- Kontrollbögen
if '--bogen' in sys.argv:
    for f in glob.glob(OUT + '/bogen-*.png') + glob.glob(OUT + '/_p-*.png'):
        os.remove(f)
    run(['pdftoppm', '-r', '58', '-png', OUT + '/handbuch.pdf', OUT + '/_p'])
    files = sorted(glob.glob(OUT + '/_p-*.png'))
    for k in range(0, len(files), 6):
        ims = [Image.open(f) for f in files[k:k + 6]]
        w, h = ims[0].size
        sheet = Image.new('RGB', (w * 3 + 20, h * 2 + 10), (120, 120, 120))
        for j, im in enumerate(ims):
            sheet.paste(im, ((j % 3) * (w + 10), (j // 3) * (h + 10)))
        sheet.save(f'{OUT}/bogen-{k // 6 + 1:02d}.png')
    for f in files:
        os.remove(f)
    print('Kontrollbögen:', OUT + '/bogen-*.png')
print('fertig:', OUT + '/handbuch.pdf', OUT + '/aushang.pdf')
