from __future__ import annotations

from copy import deepcopy
from datetime import datetime, timezone
from pathlib import Path
import xml.etree.ElementTree as ET


ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / "docs"
OUTPUT = DOCS / "DIAGRAM-GABUNGAN-FINAL-SIVMI-CRM-v2.0.drawio"

SOURCES = [
    ("CORE", DOCS / "Diagram Gabungan SIVMI CRM v1.5.drawio"),
    ("DOKUMENTASI", DOCS / "drawio" / "Diagram Tambahan Dokumentasi SIVMI CRM v1.0.drawio"),
    ("API & REST API", DOCS / "Diagram API Publik SIVMI CRM v1.0.drawio"),
    ("USE CASE", DOCS / "Diagram Use Case SIVMI CRM v1.1.drawio"),
    ("ERD", DOCS / "ERD Relasi dan Kardinalitas SIVMI CRM v1.1.drawio"),
]


def clean_name(value: str) -> str:
    value = (value or "Diagram").strip()
    while value.lower().startswith("copy of "):
        value = value[8:].strip()
    return value


def cover_page(counts: list[tuple[str, int]], total: int):
    diagram = ET.Element("diagram", {"id": "final-cover", "name": "00 - Indeks Diagram Final"})
    model = ET.SubElement(diagram, "mxGraphModel", {
        "dx": "1422", "dy": "794", "grid": "1", "gridSize": "10", "guides": "1",
        "tooltips": "1", "connect": "1", "arrows": "1", "fold": "1", "page": "1",
        "pageScale": "1", "pageWidth": "1600", "pageHeight": "900", "math": "0",
        "shadow": "0", "background": "#F6F8FC",
    })
    root = ET.SubElement(model, "root")
    ET.SubElement(root, "mxCell", {"id": "0"})
    ET.SubElement(root, "mxCell", {"id": "1", "parent": "0"})

    def vertex(cell_id, value, style, x, y, w, h):
        cell = ET.SubElement(root, "mxCell", {"id": cell_id, "value": value, "style": style, "vertex": "1", "parent": "1"})
        ET.SubElement(cell, "mxGeometry", {"x": str(x), "y": str(y), "width": str(w), "height": str(h), "as": "geometry"})

    vertex("bg", "", "rounded=1;arcSize=18;fillColor=#075985;strokeColor=#075985;", 80, 70, 1440, 220)
    vertex("title", "DIAGRAM GABUNGAN FINAL SIVMI CRM", "text;html=1;strokeColor=none;fillColor=none;fontSize=34;fontStyle=1;fontColor=#FFFFFF;align=left;verticalAlign=middle;", 130, 105, 1050, 60)
    vertex("sub", "Arsitektur, proses bisnis, data, aplikasi, teknologi, API/REST API, WAHA, testing, dan operasional", "text;html=1;strokeColor=none;fillColor=none;fontSize=18;fontColor=#BAE6FD;align=left;verticalAlign=top;whiteSpace=wrap;", 130, 175, 1180, 65)
    vertex("total", f"{total}<br><font style='font-size:14px'>halaman diagram</font>", "rounded=1;arcSize=18;fillColor=#1476B8;strokeColor=#7DD3FC;fontSize=30;fontStyle=1;fontColor=#FFFFFF;align=center;verticalAlign=middle;html=1;", 1270, 105, 190, 120)

    colors = ["#1476B8", "#10B981", "#A855F7", "#F59E0B", "#F43F5E"]
    for idx, ((label, count), color) in enumerate(zip(counts, colors)):
        x = 100 + (idx % 3) * 490
        y = 355 + (idx // 3) * 205
        vertex(f"card{idx}", "", f"rounded=1;arcSize=14;fillColor=#FFFFFF;strokeColor=#CBD5E1;shadow=1;", x, y, 440, 155)
        vertex(f"n{idx}", str(count), f"ellipse;fillColor={color};strokeColor={color};fontSize=24;fontStyle=1;fontColor=#FFFFFF;align=center;verticalAlign=middle;", x + 30, y + 32, 90, 90)
        vertex(f"l{idx}", label, "text;html=1;strokeColor=none;fillColor=none;fontSize=19;fontStyle=1;fontColor=#0B2545;align=left;verticalAlign=middle;whiteSpace=wrap;", x + 145, y + 43, 250, 70)
    vertex("foot", "Baseline 26 Agustus 2026 • seluruh objek dapat diedit di diagrams.net/draw.io", "text;html=1;strokeColor=none;fillColor=none;fontSize=14;fontColor=#64748B;align=left;verticalAlign=middle;", 100, 810, 1100, 35)
    return diagram


def main():
    missing = [str(path) for _, path in SOURCES if not path.exists()]
    if missing:
        raise FileNotFoundError("Sumber draw.io tidak ditemukan:\n" + "\n".join(missing))

    loaded: list[tuple[str, list[ET.Element]]] = []
    for category, path in SOURCES:
        root = ET.parse(path).getroot()
        pages = []
        seen_names = set()
        for diagram in root.findall("diagram"):
            original = diagram.get("name", "")
            name = clean_name(original)
            # ERD v1.1 memiliki satu duplikat eksplisit "Copy of".
            if category == "ERD" and original.lower().startswith("copy of "):
                continue
            normalized = name.lower()
            if normalized in seen_names:
                continue
            seen_names.add(normalized)
            pages.append(deepcopy(diagram))
        loaded.append((category, pages))

    total = sum(len(pages) for _, pages in loaded)
    mxfile = ET.Element("mxfile", {
        "host": "Electron",
        "modified": datetime.now(timezone.utc).isoformat(),
        "agent": "Codex SIVMI final diagram merger",
        "version": "26.0.16",
        "type": "device",
        "compressed": "false",
        "pages": str(total + 1),
    })
    counts = [(category, len(pages)) for category, pages in loaded]
    mxfile.append(cover_page(counts, total))

    page_number = 1
    for category, pages in loaded:
        for diagram in pages:
            page_number += 1
            diagram.set("id", f"final-page-{page_number:03d}")
            diagram.set("name", f"{page_number - 1:03d} [{category}] {clean_name(diagram.get('name', 'Diagram'))}")
            mxfile.append(diagram)

    ET.indent(mxfile, space="  ")
    OUTPUT.write_text(ET.tostring(mxfile, encoding="unicode", xml_declaration=True), encoding="utf-8")
    check = ET.parse(OUTPUT).getroot()
    names = [d.get("name", "") for d in check.findall("diagram")]
    if len(names) != total + 1 or len(set(names)) != len(names):
        raise RuntimeError("Audit draw.io gagal: jumlah atau nama halaman tidak valid")
    print(f"Created: {OUTPUT}")
    print(f"Pages: {len(names)} (cover + {total} diagrams)")
    for category, count in counts:
        print(f"- {category}: {count}")


if __name__ == "__main__":
    main()
