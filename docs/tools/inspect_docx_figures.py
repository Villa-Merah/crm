from pathlib import Path
import sys

from docx import Document


path = Path(sys.argv[1])
document = Document(path)
paragraphs = document.paragraphs

print(f"paragraphs={len(paragraphs)} inline_shapes={len(document.inline_shapes)}")
count = 0
for index, paragraph in enumerate(paragraphs):
    drawings = paragraph._p.xpath(".//w:drawing")
    if not drawings:
        continue
    count += len(drawings)
    before = next(
        (paragraphs[j].text.strip() for j in range(index - 1, max(-1, index - 5), -1) if paragraphs[j].text.strip()),
        "",
    )
    after = next(
        (paragraphs[j].text.strip() for j in range(index + 1, min(len(paragraphs), index + 5)) if paragraphs[j].text.strip()),
        "",
    )
    props = paragraph._p.xpath(".//wp:docPr")
    metadata = "; ".join(
        f"name={node.get('name')} descr={node.get('descr')} title={node.get('title')}" for node in props
    )
    print(
        f"{count:03d} p={index} before={before[:110]!r} after={after[:110]!r} {metadata}"
    )
