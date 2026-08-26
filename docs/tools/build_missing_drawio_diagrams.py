from __future__ import annotations

"""Build editable draw.io pages from the documentation diagram generators.

The BAB document builders draw their figures with Pillow.  This tool replaces
the Pillow canvas with a recorder, runs each non-cover diagram function, and
translates the recorded primitives into native mxGraph cells.  The resulting
file therefore contains editable shapes, text, and connectors—not embedded
PNG images.
"""

from dataclasses import dataclass, field
from datetime import datetime, timezone
from importlib import import_module
from pathlib import Path
import html
import math
import re
import sys
import xml.etree.ElementTree as ET

from PIL import Image, ImageDraw


PROJECT_ROOT = Path(__file__).resolve().parents[2]
DOCS = PROJECT_ROOT / "docs"
OUTPUT_DIR = DOCS / "drawio"
OUTPUT = OUTPUT_DIR / "Diagram Tambahan Dokumentasi SIVMI CRM v1.0.drawio"
INDEX = OUTPUT_DIR / "INDEKS-DIAGRAM-DRAWIO.md"

BUILDERS: list[tuple[str, str]] = [
    ("BAB I", "build_bab_i_pendahuluan"),
    ("BAB II", "build_bab_ii_landasan_teori"),
    ("BAB III", "build_bab_iii_analisa_kebutuhan"),
    ("BAB V", "build_bab_v_arsitektur_data"),
    ("BAB VI", "build_bab_vi_arsitektur_aplikasi"),
    ("BAB VII", "build_bab_vii_arsitektur_teknologi"),
    ("BAB VIII", "build_bab_viii_manajemen_debugging"),
    ("BAB IX", "build_bab_ix_deployment_migrasi_pemeliharaan"),
    ("BAB X", "build_bab_x_panduan_penggunaan"),
    ("BAB XI", "build_bab_xi_panduan_administrator"),
]

# Diagram ini sudah tersedia sebagai halaman native di file draw.io lain.
EXISTING_SLUGS = {
    "architecture-layer", "sitemap", "proses-bisnis", "flowmap", "flowchart",
    "dfd-level-0", "dfd-level-1", "peta-entitas", "erd-operasional",
    "erd-kinerja-kontrol", "erd-whatsapp", "dfd-whatsapp", "use-case-data",
    "konteks-api", "alur-webhook", "api-publik-v1",
}


def color(value, default="none") -> str:
    if value is None:
        return default
    if isinstance(value, tuple):
        if len(value) >= 3:
            return "#" + "".join(f"{max(0, min(255, int(v))):02X}" for v in value[:3])
        return default
    result = str(value).strip()
    if not result:
        return default
    named = {"white": "#FFFFFF", "black": "#000000"}
    if result.lower() in named:
        return named[result.lower()]
    if re.fullmatch(r"[0-9A-Fa-f]{6}", result):
        return "#" + result.upper()
    return result


def xy_box(value) -> tuple[float, float, float, float]:
    x1, y1, x2, y2 = value
    return float(x1), float(y1), float(x2), float(y2)


def points(value) -> list[tuple[float, float]]:
    values = list(value)
    if len(values) == 4 and all(isinstance(v, (int, float)) for v in values):
        return [(float(values[0]), float(values[1])), (float(values[2]), float(values[3]))]
    result = []
    for item in values:
        if isinstance(item, (tuple, list)) and len(item) >= 2:
            result.append((float(item[0]), float(item[1])))
    return result


def font_size(font, fallback=20) -> int:
    return int(getattr(font, "size", fallback) or fallback)


def font_bold(font) -> bool:
    path = str(getattr(font, "path", "")).lower()
    return any(token in path for token in ("arialbd", "calibrib", "bold"))


@dataclass
class Primitive:
    kind: str
    args: dict


@dataclass
class RecorderImage:
    size: tuple[int, int]
    background: str
    primitives: list[Primitive] = field(default_factory=list)

    @property
    def draw(self):
        return RecorderDraw(self)

    def save(self, path, *args, **kwargs):
        slug = Path(path).stem
        if slug == "cover-bab-iv" or slug.startswith("cover-"):
            return
        CAPTURED.append((CURRENT_CHAPTER, slug, self))


class RecorderDraw:
    def __init__(self, image: RecorderImage):
        self.image = image

    def add(self, kind: str, **kwargs):
        self.image.primitives.append(Primitive(kind, kwargs))

    def rounded_rectangle(self, xy, radius=0, fill=None, outline=None, width=1, **kwargs):
        self.add("rounded_rectangle", xy=xy_box(xy), radius=radius, fill=fill, outline=outline, width=width)

    def rectangle(self, xy, fill=None, outline=None, width=1, **kwargs):
        self.add("rectangle", xy=xy_box(xy), fill=fill, outline=outline, width=width)

    def ellipse(self, xy, fill=None, outline=None, width=1, **kwargs):
        self.add("ellipse", xy=xy_box(xy), fill=fill, outline=outline, width=width)

    def polygon(self, xy, fill=None, outline=None, **kwargs):
        self.add("polygon", points=points(xy), fill=fill, outline=outline)

    def line(self, xy, fill=None, width=1, **kwargs):
        self.add("line", points=points(xy), fill=fill, width=width)

    def text(self, xy, text, fill=None, font=None, anchor=None, align=None, **kwargs):
        self.add("text", xy=(float(xy[0]), float(xy[1])), text=str(text), fill=fill,
                 size=font_size(font), bold=font_bold(font), anchor=anchor, align=align)

    def multiline_text(self, xy, text, fill=None, font=None, anchor=None, align=None,
                       spacing=4, **kwargs):
        self.add("text", xy=(float(xy[0]), float(xy[1])), text=str(text), fill=fill,
                 size=font_size(font), bold=font_bold(font), anchor=anchor,
                 align=align, spacing=spacing)

    def textlength(self, text, font=None, **kwargs):
        return len(str(text)) * font_size(font) * 0.58


CAPTURED: list[tuple[str, str, RecorderImage]] = []
CURRENT_CHAPTER = ""


def pretty(slug: str) -> str:
    abbreviations = {"api": "API", "dfd": "DFD", "erd": "ERD", "rbac": "RBAC",
                     "waha": "WAHA", "qa": "QA", "crm": "CRM"}
    return " ".join(abbreviations.get(token, token.capitalize()) for token in slug.split("-"))


def add_geometry(cell, x, y, w, h, relative=False):
    attrs = {"x": f"{x:.1f}", "y": f"{y:.1f}", "width": f"{max(w, 1):.1f}",
             "height": f"{max(h, 1):.1f}", "as": "geometry"}
    if relative:
        attrs["relative"] = "1"
    return ET.SubElement(cell, "mxGeometry", attrs)


def add_vertex(root, cell_id: str, value: str, style: str, x, y, w, h):
    cell = ET.SubElement(root, "mxCell", {"id": cell_id, "value": value,
                                          "style": style, "vertex": "1", "parent": "1"})
    add_geometry(cell, x, y, w, h)


def add_edge(root, cell_id: str, start, end, stroke, width):
    style = (f"edgeStyle=none;html=1;rounded=0;strokeColor={color(stroke, '#64748B')};"
             f"strokeWidth={max(float(width), 1):.1f};endArrow=none;")
    cell = ET.SubElement(root, "mxCell", {"id": cell_id, "value": "", "style": style,
                                          "edge": "1", "parent": "1"})
    geo = ET.SubElement(cell, "mxGeometry", {"relative": "1", "as": "geometry"})
    ET.SubElement(geo, "mxPoint", {"x": f"{start[0]:.1f}", "y": f"{start[1]:.1f}", "as": "sourcePoint"})
    ET.SubElement(geo, "mxPoint", {"x": f"{end[0]:.1f}", "y": f"{end[1]:.1f}", "as": "targetPoint"})


def primitive_to_cells(root, primitive: Primitive, counter: int) -> int:
    a = primitive.args
    kind = primitive.kind
    if kind in {"rounded_rectangle", "rectangle", "ellipse"}:
        x1, y1, x2, y2 = a["xy"]
        fill = color(a.get("fill"))
        stroke = color(a.get("outline"))
        width = max(float(a.get("width", 1)), 1)
        shape = "ellipse;" if kind == "ellipse" else ("rounded=1;arcSize=12;" if kind == "rounded_rectangle" else "rounded=0;")
        style = (f"{shape}whiteSpace=wrap;html=1;fillColor={fill};strokeColor={stroke};"
                 f"strokeWidth={width:.1f};shadow=0;")
        add_vertex(root, f"c{counter}", "", style, x1, y1, x2-x1, y2-y1)
        return counter + 1
    if kind == "polygon":
        pts = a.get("points", [])
        if not pts:
            return counter
        xs, ys = [p[0] for p in pts], [p[1] for p in pts]
        x1, x2, y1, y2 = min(xs), max(xs), min(ys), max(ys)
        fill = color(a.get("fill"), "#64748B")
        style = f"shape=triangle;direction=east;whiteSpace=wrap;html=1;fillColor={fill};strokeColor={fill};"
        add_vertex(root, f"c{counter}", "", style, x1, y1, x2-x1, y2-y1)
        return counter + 1
    if kind == "line":
        pts = a.get("points", [])
        for start, end in zip(pts, pts[1:]):
            add_edge(root, f"c{counter}", start, end, a.get("fill"), a.get("width", 1))
            counter += 1
        return counter
    if kind == "text":
        text = a.get("text", "")
        lines = text.splitlines() or [""]
        size = max(int(a.get("size", 20)), 8)
        w = max(40, min(1500, max(len(line) for line in lines) * size * 0.62 + 18))
        h = max(size * 1.35, len(lines) * size * 1.35 + 8)
        x, y = a["xy"]
        anchor = a.get("anchor") or "la"
        if anchor.startswith("m"):
            x -= w / 2
        elif anchor.startswith("r"):
            x -= w
        if anchor.endswith("m"):
            y -= h / 2
        elif anchor.endswith("d") or anchor.endswith("s"):
            y -= h
        align = a.get("align") or ("center" if anchor.startswith("m") else "left")
        # ET meng-escape atribut XML saat serialisasi. Sisipkan tag HTML mentah
        # di sini agar hasil XML menjadi &lt;br&gt; (bukan &amp;lt;br&amp;gt;).
        value = html.escape(text).replace("\n", "<br>")
        style = (f"text;html=1;strokeColor=none;fillColor=none;align={align};verticalAlign=middle;"
                 f"whiteSpace=wrap;overflow=visible;fontSize={size};fontColor={color(a.get('fill'), '#0B2545')};"
                 f"fontStyle={1 if a.get('bold') else 0};spacing=0;")
        add_vertex(root, f"c{counter}", value, style, x, y, w, h)
        return counter + 1
    return counter


def make_diagram(chapter: str, slug: str, image: RecorderImage, index: int):
    graph = ET.Element("mxGraphModel", {
        "dx": "1422", "dy": "794", "grid": "1", "gridSize": "10", "guides": "1",
        "tooltips": "1", "connect": "1", "arrows": "1", "fold": "1", "page": "1",
        "pageScale": "1", "pageWidth": str(image.size[0]), "pageHeight": str(image.size[1]),
        "math": "0", "shadow": "0", "background": color(image.background, "#FFFFFF"),
    })
    root = ET.SubElement(graph, "root")
    ET.SubElement(root, "mxCell", {"id": "0"})
    ET.SubElement(root, "mxCell", {"id": "1", "parent": "0"})
    counter = 2
    for primitive in image.primitives:
        counter = primitive_to_cells(root, primitive, counter)
    name = f"{index:02d} {chapter} - {pretty(slug)}"
    return ET.Element("diagram", {"id": f"page-{index:03d}", "name": name}), graph


def capture() -> list[str]:
    global CURRENT_CHAPTER
    errors: list[str] = []
    original_new = Image.new
    original_draw = ImageDraw.Draw
    Image.new = lambda mode, size, color_value="white", *args, **kwargs: RecorderImage(tuple(size), color(color_value, "#FFFFFF"))
    ImageDraw.Draw = lambda image, *args, **kwargs: image.draw
    try:
        for chapter, module_name in BUILDERS:
            CURRENT_CHAPTER = chapter
            module = import_module(module_name)
            for name in sorted(dir(module)):
                if not name.startswith("make_") or name == "make_cover":
                    continue
                function = getattr(module, name)
                if not callable(function):
                    continue
                before = len(CAPTURED)
                try:
                    function()
                except Exception as exc:
                    errors.append(f"{chapter} {name}: {type(exc).__name__}: {exc}")
                    continue
                if len(CAPTURED) == before:
                    errors.append(f"{chapter} {name}: tidak menghasilkan halaman")
    finally:
        Image.new = original_new
        ImageDraw.Draw = original_draw
    return errors


def write_outputs(errors: list[str]):
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    pages = [(chapter, slug, image) for chapter, slug, image in CAPTURED if slug not in EXISTING_SLUGS]
    mxfile = ET.Element("mxfile", {
        "host": "Electron", "modified": datetime.now(timezone.utc).isoformat(),
        "agent": "Codex SIVMI documentation vector recorder", "version": "26.0.16",
        "type": "device", "compressed": "false", "pages": str(len(pages)),
    })
    index_rows = []
    for page_index, (chapter, slug, image) in enumerate(pages, start=1):
        diagram, graph = make_diagram(chapter, slug, image, page_index)
        diagram.append(graph)
        mxfile.append(diagram)
        index_rows.append((page_index, chapter, pretty(slug), slug, len(image.primitives)))
    ET.indent(mxfile, space="  ")
    OUTPUT.write_text(ET.tostring(mxfile, encoding="unicode", xml_declaration=True), encoding="utf-8")

    lines = [
        "# Indeks Diagram Draw.io SIVMI CRM", "",
        f"File: `{OUTPUT.name}`", "",
        "File ini berisi diagram dokumentasi yang belum tersedia sebagai halaman native pada paket draw.io sebelumnya. Semua objek berupa shape, teks, dan garis yang dapat diedit.", "",
        "| Halaman | Bab | Diagram | Sumber aset | Objek |", "|---:|---|---|---|---:|",
    ]
    lines.extend(f"| {i} | {chapter} | {title} | `{slug}.png` | {count} |" for i, chapter, title, slug, count in index_rows)
    if errors:
        lines += ["", "## Catatan generator", ""] + [f"- {error}" for error in errors]
    INDEX.write_text("\n".join(lines) + "\n", encoding="utf-8")
    return pages


def main():
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    errors = capture()
    pages = write_outputs(errors)
    print(f"Created: {OUTPUT}")
    print(f"Pages: {len(pages)}")
    print(f"Index: {INDEX}")
    if errors:
        print(f"Warnings: {len(errors)}")
        for error in errors:
            print(f"- {error}")


if __name__ == "__main__":
    main()
