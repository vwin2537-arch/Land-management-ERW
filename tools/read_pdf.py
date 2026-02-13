import sys, fitz, os

sys.stdout.reconfigure(encoding='utf-8')

pdf_path = os.path.join(os.path.dirname(__file__), '..', 'references',
    'การรังวัดสอบทานและจัดทำเครื่องหมายขอบเขตแปลงที่ดิน.pdf')

doc = fitz.open(pdf_path)
out = os.path.join(os.path.dirname(__file__), 'pdf_output.txt')

with open(out, 'w', encoding='utf-8') as f:
    for i, page in enumerate(doc):
        f.write(f"\n{'='*60}\n")
        f.write(f"  PAGE {i+1}\n")
        f.write(f"{'='*60}\n\n")
        f.write(page.get_text())

print(f"Done! {len(doc)} pages -> {out}")
doc.close()
