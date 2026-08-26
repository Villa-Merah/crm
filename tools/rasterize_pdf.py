from __future__ import annotations

import sys
from pathlib import Path

import fitz
from PIL import Image, ImageDraw


def main():
    pdf_path = Path(sys.argv[1]).resolve()
    out_dir = Path(sys.argv[2]).resolve()
    out_dir.mkdir(parents=True, exist_ok=True)
    document = fitz.open(pdf_path)

    thumbs = []
    for index, page in enumerate(document):
        pixmap = page.get_pixmap(matrix=fitz.Matrix(1.5, 1.5), alpha=False)
        target = out_dir / f"page-{index + 1}.png"
        pixmap.save(target)
        image = Image.open(target).convert("RGB")
        image.thumbnail((240, 340))
        thumbs.append((index + 1, image.copy()))

    sheet_width = 1000
    cell_width, cell_height = 250, 380
    pages_per_sheet = 16
    for batch_start in range(0, len(thumbs), pages_per_sheet):
        batch = thumbs[batch_start:batch_start + pages_per_sheet]
        rows = (len(batch) + 3) // 4
        sheet = Image.new("RGB", (sheet_width, rows * cell_height), "#E5E7EB")
        draw = ImageDraw.Draw(sheet)
        for offset, (page_number, thumb) in enumerate(batch):
            x = (offset % 4) * cell_width + 5
            y = (offset // 4) * cell_height + 28
            sheet.paste(thumb, (x + (240 - thumb.width) // 2, y))
            draw.text((x + 6, 6 + (offset // 4) * cell_height), f"Halaman {page_number}", fill="#111827")
        sheet.save(out_dir / f"contact-{batch_start + 1}-{batch_start + len(batch)}.png")

    print(f"pages={len(document)}")


if __name__ == "__main__":
    main()
