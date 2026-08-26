from __future__ import annotations

from pathlib import Path
import sys


PROJECT_ROOT = Path(__file__).resolve().parents[2]
DOC_TOOLS = PROJECT_ROOT / "storage" / "app" / "doc-tools"
sys.path.insert(0, str(DOC_TOOLS))

import fitz
from PIL import Image, ImageDraw, ImageOps


PDF = PROJECT_ROOT / "docs" / "_qa_bab_i_xi" / "Dokumentasi SIVMI CRM BAB I-XI v1.0.pdf"
OUTPUT = PROJECT_ROOT / "docs" / "_qa_bab_i_xi" / "pages"
CONTACTS = PROJECT_ROOT / "docs" / "_qa_bab_i_xi" / "contact-sheets"


def main() -> None:
    OUTPUT.mkdir(parents=True, exist_ok=True)
    CONTACTS.mkdir(parents=True, exist_ok=True)
    document = fitz.open(PDF)
    thumbnails: list[tuple[int, Image.Image]] = []
    blank_pages: list[int] = []

    for index, page in enumerate(document):
        pixmap = page.get_pixmap(matrix=fitz.Matrix(1.5, 1.5), alpha=False)
        image = Image.frombytes("RGB", (pixmap.width, pixmap.height), pixmap.samples)
        image.save(OUTPUT / f"page-{index + 1:03d}.png", optimize=True)

        grayscale = ImageOps.grayscale(image)
        ink = sum(1 for value in grayscale.resize((120, 160)).getdata() if value < 245)
        if ink < 35:
            blank_pages.append(index + 1)

        thumbnail = image.copy()
        thumbnail.thumbnail((260, 340))
        thumbnails.append((index + 1, thumbnail))

    per_sheet = 12
    for sheet_index in range(0, len(thumbnails), per_sheet):
        group = thumbnails[sheet_index:sheet_index + per_sheet]
        sheet = Image.new("RGB", (1200, 1140), "#DCE3EC")
        draw = ImageDraw.Draw(sheet)
        for position, (page_number, thumbnail) in enumerate(group):
            column = position % 4
            row = position // 4
            x = 20 + column * 295
            y = 38 + row * 370
            page_x = x + (260 - thumbnail.width) // 2
            page_y = y + (330 - thumbnail.height) // 2
            sheet.paste(thumbnail, (page_x, page_y))
            draw.text((x, 12 + row * 370), f"Halaman {page_number}", fill="#0F172A")
        sheet.save(CONTACTS / f"contact-{sheet_index // per_sheet + 1:02d}.png", optimize=True)

    report = [
        f"page_count={len(document)}",
        f"blank_pages={','.join(map(str, blank_pages)) if blank_pages else 'none'}",
        f"contact_sheet_count={(len(thumbnails) + per_sheet - 1) // per_sheet}",
    ]
    (OUTPUT.parent / "qa-report.txt").write_text("\n".join(report), encoding="utf-8")
    print("\n".join(report))


if __name__ == "__main__":
    main()
