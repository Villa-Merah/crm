from __future__ import annotations

import argparse
import math
from pathlib import Path

import pymupdf
from PIL import Image, ImageDraw


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--dpi", type=int, default=144)
    parser.add_argument("--sheet-columns", type=int, default=3)
    parser.add_argument("--sheet-rows", type=int, default=3)
    args = parser.parse_args()

    args.output.mkdir(parents=True, exist_ok=True)
    page_dir = args.output / "pages"
    sheet_dir = args.output / "sheets"
    page_dir.mkdir(exist_ok=True)
    sheet_dir.mkdir(exist_ok=True)

    document = pymupdf.open(args.pdf)
    scale = args.dpi / 72
    page_paths: list[Path] = []

    for page_index, page in enumerate(document):
        pixmap = page.get_pixmap(matrix=pymupdf.Matrix(scale, scale), alpha=False)
        page_path = page_dir / f"page-{page_index + 1:03d}.png"
        pixmap.save(page_path)
        page_paths.append(page_path)

    per_sheet = args.sheet_columns * args.sheet_rows
    thumb_width = 760
    label_height = 34
    gap = 20

    for sheet_index in range(math.ceil(len(page_paths) / per_sheet)):
        subset = page_paths[sheet_index * per_sheet:(sheet_index + 1) * per_sheet]
        thumbs = []
        for page_path in subset:
            image = Image.open(page_path).convert("RGB")
            ratio = thumb_width / image.width
            thumb = image.resize((thumb_width, round(image.height * ratio)), Image.Resampling.LANCZOS)
            thumbs.append((page_path, thumb))

        thumb_height = max(image.height for _, image in thumbs)
        canvas_width = gap + args.sheet_columns * (thumb_width + gap)
        canvas_height = gap + args.sheet_rows * (label_height + thumb_height + gap)
        canvas = Image.new("RGB", (canvas_width, canvas_height), "#D9DEE8")
        draw = ImageDraw.Draw(canvas)

        for item_index, (page_path, image) in enumerate(thumbs):
            row = item_index // args.sheet_columns
            column = item_index % args.sheet_columns
            x = gap + column * (thumb_width + gap)
            y = gap + row * (label_height + thumb_height + gap)
            draw.text((x + 8, y + 7), page_path.stem.replace("page-", "Halaman "), fill="#10213F")
            canvas.paste(image, (x, y + label_height))

        first_page = sheet_index * per_sheet + 1
        last_page = first_page + len(subset) - 1
        canvas.save(sheet_dir / f"sheet-{first_page:03d}-{last_page:03d}.jpg", quality=88)

    print(f"pages={document.page_count}")
    print(f"sheets={math.ceil(len(page_paths) / per_sheet)}")


if __name__ == "__main__":
    main()
