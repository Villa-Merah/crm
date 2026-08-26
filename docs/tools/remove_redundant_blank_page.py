from pathlib import Path

from docx import Document


DOCX = Path(__file__).resolve().parents[1] / "DOKUMENTASI-FINAL-PROJECT-SIVMI-CRM-v2.0.docx"


def main() -> None:
    document = Document(DOCX)
    paragraphs = document.paragraphs
    heading_index = next(
        index
        for index, paragraph in enumerate(paragraphs)
        if paragraph.text.strip().startswith("9.18 Kriteria Dokumen")
    )
    separator = paragraphs[heading_index - 1]

    if separator.text.strip() or "<w:br" not in separator._p.xml:
        raise RuntimeError("Pemisah halaman berlebih sebelum bagian 9.18 tidak ditemukan.")

    separator._element.getparent().remove(separator._element)
    temporary = DOCX.with_suffix(".blank-page-fix.tmp.docx")
    document.save(temporary)
    temporary.replace(DOCX)
    print(f"removed_redundant_page_break={DOCX}")


if __name__ == "__main__":
    main()
