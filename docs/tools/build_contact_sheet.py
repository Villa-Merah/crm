from pathlib import Path
import sys

PROJECT_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(PROJECT_ROOT / "storage" / "app" / "doc-tools"))

from PIL import Image, ImageDraw


def main() -> None:
    if len(sys.argv) < 4:
        raise SystemExit("Usage: build_contact_sheet.py OUTPUT.png INPUT.png INPUT.png ...")

    output = Path(sys.argv[1]).resolve()
    inputs = [Path(value).resolve() for value in sys.argv[2:]]
    thumbnails = []
    for input_path in inputs:
        image = Image.open(input_path).convert("RGB")
        image.thumbnail((620, 880))
        thumbnails.append((input_path.name, image.copy()))

    columns = 2
    rows = (len(thumbnails) + columns - 1) // columns
    sheet = Image.new("RGB", (1300, rows * 940), "#DDE3EB")
    draw = ImageDraw.Draw(sheet)
    for index, (name, image) in enumerate(thumbnails):
        x = 20 + (index % columns) * 640
        y = 20 + (index // columns) * 940
        draw.text((x, y), name, fill="#111827")
        sheet.paste(image, (x, y + 28))

    output.parent.mkdir(parents=True, exist_ok=True)
    sheet.save(output)
    print(output)


if __name__ == "__main__":
    main()
