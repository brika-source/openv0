#!/usr/bin/env python3
"""
Generate the demo's section imagery.

No image host is reachable from this environment, so photographs cannot be
downloaded or embedded. These are built locally instead: saturated mesh-gradient
fields carrying the instrument each service is actually known by — an audiogram
notch, a flow-volume loop, a compression trace, joint-angle arcs, recovery
curves.

Architecture: the colour field is painted small and upscaled (cheap, and it
gives a smooth mesh gradient); the line work is drawn at 3x and downsampled, so
curves stay crisp.

    python3 tools/make-artwork.py     ->  demo/art/*.jpg
"""
import math, os, random
from PIL import Image, ImageDraw, ImageFilter, ImageEnhance

W, H, SS = 1600, 1000, 3
OUT = os.path.join(os.path.dirname(__file__), '..', 'demo', 'art')


def hx(s):
    s = s.lstrip('#')
    return tuple(int(s[i:i + 2], 16) for i in (0, 2, 4))


def mesh(base, blobs, size=(200, 125)):
    """
    Paint a handful of saturated radial blobs at thumbnail size, blur hard, then
    upscale. Produces a rich mesh gradient for a fraction of the pixel work.
    blobs: (x, y, radius, colour, strength) in 0..1 coordinates.
    """
    sw, sh = size
    img = Image.new('RGB', (sw, sh), base)
    for fx, fy, fr, col, strength in blobs:
        layer = Image.new('RGB', (sw, sh), col)
        mask = Image.new('L', (sw, sh), 0)
        d = ImageDraw.Draw(mask)
        cx, cy, r = fx * sw, fy * sh, fr * sw
        steps = 26
        for i in range(steps, 0, -1):
            rr = r * i / steps
            d.ellipse([cx - rr, cy - rr, cx + rr, cy + rr],
                      fill=int(255 * strength * (1 - i / steps) ** 1.35))
        mask = mask.filter(ImageFilter.GaussianBlur(r * 0.22))
        img = Image.composite(layer, img, mask)
    img = img.filter(ImageFilter.GaussianBlur(2.2))
    return img.resize((W * SS, H * SS), Image.BICUBIC)


def catmull(points, samples=26):
    if len(points) < 3:
        return list(points)
    p = [points[0]] + list(points) + [points[-1]]
    out = []
    for i in range(len(p) - 3):
        p0, p1, p2, p3 = p[i], p[i + 1], p[i + 2], p[i + 3]
        for s in range(samples):
            t = s / samples
            t2, t3 = t * t, t * t * t
            out.append((
                0.5 * ((2 * p1[0]) + (-p0[0] + p2[0]) * t +
                       (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2 +
                       (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3),
                0.5 * ((2 * p1[1]) + (-p0[1] + p2[1]) * t +
                       (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2 +
                       (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3),
            ))
    out.append(points[-1])
    return out


def stroke(d, pts, colour, width, halo=(255, 255, 255, 64), halo_extra=11):
    """A bright line with a soft halo, so it reads against a busy field."""
    if halo:
        d.line(pts, fill=halo, width=int((width + halo_extra) * SS), joint='curve')
    d.line(pts, fill=colour + (255,), width=int(width * SS), joint='curve')


def grid(d, colour, step=118, width=2):
    w, h = W * SS, H * SS
    for x in range(step * SS, w, step * SS):
        d.line([(x, 0), (x, h)], fill=colour, width=width * SS)
    for y in range(step * SS, h, step * SS):
        d.line([(0, y), (w, y)], fill=colour, width=width * SS)


def node(d, x, y, r, fill=(255, 255, 255), core=None):
    d.ellipse([x - r, y - r, x + r, y + r], fill=fill + (255,))
    if core:
        r2 = r * 0.45
        d.ellipse([x - r2, y - r2, x + r2, y + r2], fill=core + (255,))


def finish(img, saturation=1.22, contrast=1.08, vignette=0.30):
    img = img.resize((W, H), Image.LANCZOS)
    if vignette:
        mask = Image.new('L', (W, H), 0)
        d = ImageDraw.Draw(mask)
        steps = 30
        for i in range(steps):
            f = i / steps
            d.ellipse([-W * .28 + W * .62 * f, -H * .28 + H * .62 * f,
                       W * 1.28 - W * .62 * f, H * 1.28 - H * .62 * f],
                      fill=int(255 * (1 - f) ** 2 * vignette))
        img = Image.composite(Image.new('RGB', (W, H), (0, 0, 0)), img,
                              mask.filter(ImageFilter.GaussianBlur(120)))
    img = ImageEnhance.Color(img).enhance(saturation)
    return ImageEnhance.Contrast(img).enhance(contrast)


# --------------------------------------------------------------- artworks ---

def audiometry():
    """Hero: the noise-notch audiogram, over radiating wavefronts."""
    w, h = W * SS, H * SS
    img = mesh(hx('#032A33'), [
        (0.78, 0.14, 0.70, hx('#00E5C0'), 0.95),
        (0.20, 0.30, 0.55, hx('#0E86C4'), 0.80),
        (0.12, 0.92, 0.55, hx('#FF6B35'), 0.60),
        (0.55, 0.62, 0.45, hx('#0B4F62'), 0.55),
    ])
    d = ImageDraw.Draw(img, 'RGBA')
    grid(d, (255, 255, 255, 26))

    for i in range(1, 15):                       # wavefronts from the ear
        r = (60 + i * 118) * SS
        d.ellipse([w * .18 - r, h * .54 - r, w * .18 + r, h * .54 + r],
                  outline=(255, 255, 255, max(8, 66 - i * 4)), width=int(2.6 * SS))

    y = h * .585                                  # action level
    for x in range(0, w, int(36 * SS)):
        d.line([(x, y), (x + 19 * SS, y)], fill=hx('#FFB088') + (225,), width=int(5 * SS))

    base = [(.13, .28), (.27, .30), (.41, .34), (.55, .44), (.68, .74), (.81, .57), (.93, .50)]
    pts = [(x * w, y_ * h) for x, y_ in base]
    stroke(d, catmull(pts), hx('#C9FFF0'), 8)
    for x, y_ in pts:
        node(d, x, y_, 15 * SS, (255, 255, 255), hx('#046B62'))
    return finish(img)


def spirometry():
    """Surveillance: the flow-volume loop."""
    w, h = W * SS, H * SS
    img = mesh(hx('#04293A'), [
        (0.26, 0.18, 0.62, hx('#00D6B4'), 0.95),
        (0.86, 0.72, 0.60, hx('#2F9BF0'), 0.80),
        (0.52, 0.96, 0.45, hx('#00FFD0'), 0.45),
        (0.05, 0.62, 0.40, hx('#083A55'), 0.60),
    ])
    d = ImageDraw.Draw(img, 'RGBA')
    grid(d, (255, 255, 255, 24), step=104)

    d.line([(w * .11, h * .52), (w * .94, h * .52)], fill=(255, 255, 255, 90), width=int(3.5 * SS))
    d.line([(w * .11, h * .10), (w * .11, h * .92)], fill=(255, 255, 255, 90), width=int(3.5 * SS))

    exp = [(.13, .52), (.22, .17), (.34, .24), (.50, .35), (.69, .45), (.88, .52)]
    ins = [(.88, .52), (.71, .74), (.50, .81), (.29, .74), (.13, .52)]
    stroke(d, catmull([(x * w, y * h) for x, y in exp]), hx('#EAFFFA'), 9)
    stroke(d, catmull([(x * w, y * h) for x, y in ins]), hx('#6FE8D2'), 7, halo=(255, 255, 255, 45))
    node(d, .22 * w, .17 * h, 17 * SS, hx('#FF8A4C'))
    return finish(img)


def emergency():
    """Emergency: a compression trace at the ERC 30:2 rhythm."""
    w, h = W * SS, H * SS
    img = mesh(hx('#250A10'), [
        (0.18, 0.20, 0.62, hx('#FF4E2B'), 0.95),
        (0.82, 0.78, 0.62, hx('#FFB020'), 0.85),
        (0.52, 0.42, 0.45, hx('#C4181A'), 0.70),
        (0.94, 0.10, 0.35, hx('#FF9A5A'), 0.55),
    ])
    d = ImageDraw.Draw(img, 'RGBA')
    grid(d, (255, 255, 255, 28), step=92)

    # Ten compressions then two ventilations: the 30:2 cycle, shown at a
    # spacing you can actually read rather than 30 spikes of picket fence.
    mid = h * .52
    pts, x = [(w * .04, mid)], w * .04
    step = w * .075
    for i in range(10):
        amp = .30 + (.03 if i % 3 == 0 else 0)
        pts += [(x + step * .18, mid - h * .03),
                (x + step * .36, mid - h * amp),
                (x + step * .50, mid + h * .15),
                (x + step * .64, mid - h * .02),
                (x + step * .92, mid)]
        x += step
    for _ in range(2):
        pts += [(x + step * .22, mid - h * .11), (x + step * .55, mid - h * .13),
                (x + step * .88, mid)]
        x += step
    pts.append((w * .98, mid))

    d.line([(w * .05, mid), (w * .97, mid)], fill=(255, 255, 255, 70), width=int(2.5 * SS))
    d.line(pts, fill=(60, 8, 8, 110), width=int(19 * SS), joint='curve')
    d.line(pts, fill=hx('#FFF3E4') + (255,), width=int(6.5 * SS), joint='curve')
    bx = w * .845
    d.rectangle([bx, h * .10, bx + 7 * SS, h * .92], fill=hx('#FFD9AE') + (170,))
    return finish(img, saturation=1.18, vignette=0.34)


def ergonomics():
    """Ergonomics: joint-angle arcs over a working posture."""
    w, h = W * SS, H * SS
    img = mesh(hx('#0B2E22'), [
        (0.74, 0.18, 0.66, hx('#FFC93C'), 0.92),
        (0.16, 0.74, 0.60, hx('#25C98E'), 0.85),
        (0.92, 0.86, 0.42, hx('#FF8A2B'), 0.60),
        (0.36, 0.30, 0.40, hx('#0F5A46'), 0.55),
    ])
    d = ImageDraw.Draw(img, 'RGBA')
    grid(d, (255, 255, 255, 24), step=108)

    ink, lw = (255, 255, 255, 240), int(10 * SS)
    hd, hr = (w * .40, h * .25), 44 * SS
    d.ellipse([hd[0] - hr, hd[1] - hr, hd[0] + hr, hd[1] + hr], outline=ink, width=lw)
    d.line([(w * .425, h * .32), (w * .50, h * .45), (w * .565, h * .60)], fill=ink, width=lw, joint='curve')
    d.line([(w * .47, h * .39), (w * .35, h * .51), (w * .285, h * .62)], fill=ink, width=lw, joint='curve')
    d.line([(w * .565, h * .60), (w * .59, h * .78), (w * .535, h * .91)], fill=ink, width=lw, joint='curve')
    d.line([(w * .17, h * .635), (w * .75, h * .635)], fill=(255, 255, 255, 170), width=int(7 * SS))

    for (cx, cy), r, a0, a1, col in (
        ((w * .50, h * .45), 124 * SS, 198, 312, hx('#FFF3C8')),
        ((w * .35, h * .51), 100 * SS, 298, 32, hx('#9BFFDE')),
        ((w * .565, h * .60), 108 * SS, 248, 342, hx('#FFC9A0')),
    ):
        d.arc([cx - r, cy - r, cx + r, cy + r], a0, a1, fill=col + (255,), width=int(8 * SS))
    return finish(img, saturation=1.2, vignette=0.32)


def mental_health():
    """Mental health: strain and recovery, three markers along the middle band."""
    w, h = W * SS, H * SS
    img = mesh(hx('#170E42'), [
        (0.80, 0.20, 0.66, hx('#A66BFF'), 0.95),
        (0.18, 0.78, 0.62, hx('#3FD0E0'), 0.85),
        (0.58, 0.96, 0.45, hx('#FF7AA8'), 0.65),
        (0.06, 0.14, 0.40, hx('#2B1A6E'), 0.60),
    ])
    d = ImageDraw.Draw(img, 'RGBA')
    random.seed(11)

    for base, col, wd in ((.29, hx('#FFC7D8'), 9), (.47, hx('#B8FFF2'), 8), (.66, hx('#D8C4FF'), 7)):
        ctrl = [(w * (.01 + i * .124),
                 h * (base + math.sin(i * 1.15 + base * 9) * .08 + random.uniform(-.02, .02)))
                for i in range(10)]
        stroke(d, catmull(ctrl), col, wd, halo=(255, 255, 255, 50))

    for fx in (.24, .5, .76):
        cy = h * (.47 + math.sin(fx * 6) * .05)
        node(d, w * fx, cy, 22 * SS, (255, 255, 255), hx('#4A2A8C'))
    return finish(img, saturation=1.26, vignette=0.34)


ART = [
    ('01-audiometry.jpg', audiometry),
    ('02-spirometry.jpg', spirometry),
    ('03-emergency.jpg', emergency),
    ('04-ergonomics.jpg', ergonomics),
    ('05-mental-health.jpg', mental_health),
]

os.makedirs(OUT, exist_ok=True)
for name, fn in ART:
    im = fn()
    p = os.path.join(OUT, name)
    im.save(p, 'JPEG', quality=80, optimize=True, progressive=True)
    print(f'  {name:22} {os.path.getsize(p) / 1024:6.1f} KB  {im.size[0]}x{im.size[1]}')
print(f'wrote {len(ART)} artworks -> demo/art/')
