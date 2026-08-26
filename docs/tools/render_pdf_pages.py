from pathlib import Path
import sys

PROJECT_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(PROJECT_ROOT / "storage" / "app" / "doc-tools"))

import pymupdf


def main() -> None:
    if len(sys.argv) != 3:
        raise SystemExit("Usage: render_pdf_pages.py INPUT.pdf OUTPUT_DIR")

    input_pdf = Path(sys.argv[1]).resolve()
    output_dir = Path(sys.argv[2]).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    document = pymupdf.open(input_pdf)
    matrix = pymupdf.Matrix(1.5, 1.5)

    for index, page in enumerate(document, start=1):
        pixmap = page.get_pixmap(matrix=matrix, alpha=False)
        pixmap.save(output_dir / f"page-{index:03d}.png")

    print(f"Rendered {document.page_count} pages to {output_dir}")


if __name__ == "__main__":
    main()
