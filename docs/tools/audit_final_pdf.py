from pathlib import Path
import json
import fitz


PDF = Path("docs/_qa_final_project.pdf")
OUT = Path("docs/_qa_final_project_audit.json")

patterns = [
    "DOKUMENTASI FINAL PROJECT",
    "RINGKASAN EKSEKUTIF",
    "STRUKTUR DOKUMEN",
    *[f"BAB {roman}" for roman in ("I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI")],
    "LAMPIRAN A",
    "LAMPIRAN B",
    "LAMPIRAN C",
]

doc = fitz.open(PDF)
first_pages = {}
low_text_pages = []

for index, page in enumerate(doc):
    raw_text = page.get_text("text")
    lines = [line.strip() for line in raw_text.splitlines() if line.strip()]
    text = " ".join(raw_text.split())
    if len(text) < 25:
        low_text_pages.append({"page": index + 1, "characters": len(text)})
    upper_lines = [line.upper() for line in lines]
    for pattern in patterns:
        target = pattern.upper()
        matches_heading = any(line == target or line.startswith(target + ":") for line in upper_lines)
        if index >= 2 and pattern not in first_pages and matches_heading:
            first_pages[pattern] = index + 1

result = {
    "pages": len(doc),
    "first_pages": first_pages,
    "low_text_pages": low_text_pages,
}
OUT.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
print(json.dumps(result, indent=2, ensure_ascii=False))
