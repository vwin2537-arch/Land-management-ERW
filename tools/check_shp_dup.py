"""
เช็คว่า records ที่ SPAR_CODE + NUM_APAR ซ้ำกันใน .dbf/.shp
มี polygon geometry ต่างกันหรือไม่

ถ้า polygon ต่างกัน = ลบไม่ได้ (จะสูญเสีย geometry)
ถ้า polygon เหมือนกัน = ลบ DUP ได้อย่างปลอดภัย
"""
import shapefile
import hashlib

SHP_PATH = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\ตรวจสอบคุณสมบัติ\Merge_แปลงสอบทาน"

print("Loading shapefile...")
sf = shapefile.Reader(SHP_PATH, encoding='utf-8')
fields = [f[0] for f in sf.fields[1:]]  # skip DeletionFlag

print(f"Total records: {len(sf)}")
print(f"Fields: {fields}")

# Find column indices
spar_idx = fields.index('SPAR_CODE') if 'SPAR_CODE' in fields else None
numapar_idx = fields.index('NUM_APAR') if 'NUM_APAR' in fields else None
fid_idx = fields.index('FID') if 'FID' in fields else None

print(f"SPAR_CODE col: {spar_idx}, NUM_APAR col: {numapar_idx}, FID col: {fid_idx}")

# Build map: (SPAR_CODE, NUM_APAR) -> list of (record_index, geometry_hash, bbox)
from collections import defaultdict
combo_map = defaultdict(list)

for i in range(len(sf)):
    rec = sf.record(i)
    shp = sf.shape(i)
    
    spar = str(rec[spar_idx]).strip() if spar_idx is not None else ''
    numapar = str(rec[numapar_idx]).strip() if numapar_idx is not None else ''
    fid = str(rec[fid_idx]).strip() if fid_idx is not None else str(i)
    
    # Hash the geometry points for comparison
    pts_str = str(shp.points) if shp.points else ''
    geo_hash = hashlib.md5(pts_str.encode()).hexdigest()[:12]
    bbox = shp.bbox if hasattr(shp, 'bbox') and shp.bbox else None
    num_points = len(shp.points) if shp.points else 0
    
    combo_map[(spar, numapar)].append({
        'idx': i, 'fid': fid, 'geo_hash': geo_hash,
        'bbox': bbox, 'num_points': num_points
    })

# Find duplicates
print(f"\n{'='*70}")
print(f"  ผลการเปรียบเทียบ Shapefile geometry กับ records ที่ SPAR_CODE+NUM_APAR ซ้ำ")
print(f"{'='*70}")

same_geo = []  # same geometry = safe to delete
diff_geo = []  # different geometry = NOT safe to delete

for (spar, numapar), entries in combo_map.items():
    if len(entries) <= 1:
        continue
    
    # Check if all geometries are the same
    hashes = set(e['geo_hash'] for e in entries)
    
    if len(hashes) == 1:
        same_geo.append((spar, numapar, entries))
    else:
        diff_geo.append((spar, numapar, entries))

print(f"\n  Records ที่มี SPAR_CODE+NUM_APAR ซ้ำ:")
print(f"    Geometry เหมือนกัน (ลบได้): {len(same_geo)} กลุ่ม")
print(f"    Geometry ต่างกัน (ลบไม่ได้!): {len(diff_geo)} กลุ่ม")

if same_geo:
    print(f"\n{'─'*70}")
    print(f"  ✅ Geometry เหมือนกัน — ลบ DUP ได้ปลอดภัย ({len(same_geo)} กลุ่ม)")
    print(f"{'─'*70}")
    for spar, numapar, entries in same_geo[:10]:
        print(f"\n  SPAR={spar}  NUM_APAR={numapar}  ({len(entries)} records)")
        for e in entries:
            print(f"    FID={e['fid']}  shp_row={e['idx']}  geo_hash={e['geo_hash']}  points={e['num_points']}  bbox={e['bbox']}")

if diff_geo:
    print(f"\n{'─'*70}")
    print(f"  ⚠️ Geometry ต่างกัน — ลบไม่ได้! จะสูญเสีย polygon ({len(diff_geo)} กลุ่ม)")
    print(f"{'─'*70}")
    for spar, numapar, entries in diff_geo:
        print(f"\n  SPAR={spar}  NUM_APAR={numapar}  ({len(entries)} records)")
        for e in entries:
            print(f"    FID={e['fid']}  shp_row={e['idx']}  geo_hash={e['geo_hash']}  points={e['num_points']}  bbox={e['bbox']}")
else:
    print(f"\n  ✅ ไม่มี record ที่ geometry ต่างกัน — ลบ DUP ได้ปลอดภัยทั้งหมด!")

print(f"\n{'='*70}")
print("Done!")
