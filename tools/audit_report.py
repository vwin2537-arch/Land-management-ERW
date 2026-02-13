"""
สร้างรายงานข้อมูลที่ต้องตรวจสอบกับ hard paper
- เลขบัตรประชาชนที่ไม่ผ่าน checksum
- ชื่อ/สกุลที่มีความผิดปกติ
- ข้อมูลที่ขาดหาย
- แปลงที่มี data_issues
"""
import pymysql
import io
import os
import re
from urllib.parse import urlparse

# ============================================================
# Config
# ============================================================
ENV_PATH  = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\.env"
REPORT_PATH = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\tools\audit_hardpaper.txt"

# Read .env
def read_env(path):
    env = {}
    if not os.path.exists(path):
        return env
    with open(path, encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if '=' in line:
                k, v = line.split('=', 1)
                env[k.strip()] = v.strip()
    return env

env = read_env(ENV_PATH)
mysql_url = env.get('MYSQL_URL', '') or env.get('MYSQLDATABASE_URL', '')
if mysql_url:
    p = urlparse(mysql_url)
    DB_HOST = p.hostname or '127.0.0.1'
    DB_PORT = p.port or 3306
    DB_USER = p.username or 'root'
    DB_PASS = p.password or ''
    DB_NAME = (p.path or '/land_management').lstrip('/')
else:
    DB_HOST = env.get('DB_HOST', '127.0.0.1')
    DB_PORT = int(env.get('DB_PORT', '3306'))
    DB_USER = env.get('DB_USER', 'root')
    DB_PASS = env.get('DB_PASS', '')
    DB_NAME = env.get('DB_NAME', 'land_management')

# ============================================================
# Thai ID validation
# ============================================================
def validate_idcard(idc):
    issues = []
    if not idc:
        issues.append('ไม่มีเลขบัตร')
        return issues
    if idc.startswith('TEMP_'):
        issues.append(f'เลขบัตรชั่วคราว: {idc}')
        return issues
    if len(idc) != 13:
        issues.append(f'ไม่ครบ 13 หลัก ({len(idc)} หลัก)')
    if not idc.isdigit():
        issues.append('มีตัวอักษรปน')
    elif len(idc) == 13:
        s = sum(int(idc[i]) * (13 - i) for i in range(12))
        check = (11 - (s % 11)) % 10
        if check != int(idc[12]):
            issues.append(f'checksum ผิด (หลักสุดท้ายควรเป็น {check} แต่เป็น {idc[12]})')
        if idc.startswith('0'):
            issues.append('ขึ้นต้นด้วย 0 (น่าสงสัย)')
    return issues

# ============================================================
# Name validation
# ============================================================
def validate_name(name, label):
    issues = []
    if not name or name in ('ไม่ระบุ',):
        issues.append(f'{label}: ว่าง/ไม่ระบุ')
        return issues
    if re.search(r'[0-9]', name):
        issues.append(f'{label}: มีตัวเลขปน "{name}"')
    if re.search(r'[!@#$%^&*()=+\[\]{}<>|\\/:;]', name):
        issues.append(f'{label}: มีอักขระพิเศษ "{name}"')
    if '_x000D_' in name or '\r' in name or '\n' in name:
        issues.append(f'{label}: มี artifact/ขึ้นบรรทัดใหม่ "{repr(name)}"')
    if len(name.strip()) <= 1:
        issues.append(f'{label}: สั้นเกินไป "{name}"')
    if name.strip() != name:
        issues.append(f'{label}: มีช่องว่างนำหน้า/ต่อท้าย')
    return issues

# ============================================================
# Connect & Query
# ============================================================
conn = pymysql.connect(
    host=DB_HOST, port=DB_PORT, user=DB_USER, password=DB_PASS,
    database=DB_NAME, charset='utf8mb4', connect_timeout=10
)
cur = conn.cursor(pymysql.cursors.DictCursor)

report = []
def rpt(msg=''):
    report.append(msg)

rpt("=" * 70)
rpt("  รายงานข้อมูลที่ต้องตรวจสอบกับ Hard Paper")
rpt(f"  วันที่สร้าง: {__import__('datetime').datetime.now().strftime('%Y-%m-%d %H:%M')}")
rpt("=" * 70)

# ============================================================
# 1. แปลงที่มี data_issues จาก import
# ============================================================
cur.execute("""
    SELECT lp.plot_code, lp.num_apar, lp.data_issues,
           v.id_card_number, v.prefix, v.first_name, v.last_name
    FROM land_plots lp
    LEFT JOIN villagers v ON lp.villager_id = v.villager_id
    WHERE lp.data_issues IS NOT NULL
    ORDER BY lp.plot_code
""")
di_rows = cur.fetchall()

rpt(f"\n{'─'*70}")
rpt(f"  1. แปลงที่ถูก flag ว่ามีปัญหาตอน import ({len(di_rows)} รายการ)")
rpt(f"{'─'*70}")
for i, r in enumerate(di_rows, 1):
    rpt(f"  {i:3d}. [{r['plot_code']}] NUM_APAR={r['num_apar'] or '-'}")
    rpt(f"       เลขบัตร: {r['id_card_number']} | ชื่อ: {r['prefix'] or ''}{r['first_name']} {r['last_name']}")
    rpt(f"       ปัญหา: {r['data_issues']}")

# ============================================================
# 2. เลขบัตรประชาชนที่ไม่ถูกต้อง (ทุกคนใน villagers)
# ============================================================
cur.execute("SELECT villager_id, id_card_number, prefix, first_name, last_name FROM villagers ORDER BY villager_id")
all_villagers = cur.fetchall()

bad_id_rows = []
for v in all_villagers:
    id_issues = validate_idcard(v['id_card_number'])
    if id_issues:
        bad_id_rows.append((v, id_issues))

rpt(f"\n{'─'*70}")
rpt(f"  2. ราษฎรที่เลขบัตรประชาชนไม่ถูกต้อง ({len(bad_id_rows)} คน)")
rpt(f"{'─'*70}")
for i, (v, issues) in enumerate(bad_id_rows, 1):
    rpt(f"  {i:3d}. ID={v['villager_id']} | เลขบัตร: {v['id_card_number']}")
    rpt(f"       ชื่อ: {v['prefix'] or ''}{v['first_name']} {v['last_name']}")
    for iss in issues:
        rpt(f"       ❌ {iss}")

# ============================================================
# 3. ชื่อ/สกุลที่มีความผิดปกติ
# ============================================================
bad_name_rows = []
for v in all_villagers:
    name_issues = []
    name_issues.extend(validate_name(v['first_name'], 'ชื่อ'))
    name_issues.extend(validate_name(v['last_name'], 'สกุล'))
    if v['prefix'] and len(v['prefix'].strip()) <= 1:
        name_issues.append(f'คำนำหน้า: สั้นเกินไป "{v["prefix"]}"')
    if name_issues:
        bad_name_rows.append((v, name_issues))

rpt(f"\n{'─'*70}")
rpt(f"  3. ราษฎรที่ชื่อ/สกุลมีความผิดปกติ ({len(bad_name_rows)} คน)")
rpt(f"{'─'*70}")
for i, (v, issues) in enumerate(bad_name_rows, 1):
    rpt(f"  {i:3d}. ID={v['villager_id']} | เลขบัตร: {v['id_card_number']}")
    rpt(f"       ชื่อ: {v['prefix'] or ''}{v['first_name']} {v['last_name']}")
    for iss in issues:
        rpt(f"       ⚠️ {iss}")

# ============================================================
# 4. แปลงที่ขาดข้อมูลสำคัญ
# ============================================================
cur.execute("""
    SELECT lp.plot_code, lp.num_apar, lp.spar_code, lp.latitude, lp.longitude,
           lp.area_rai, lp.area_ngan, lp.area_sqwa, lp.ptype, lp.occupation_since,
           v.id_card_number, v.first_name, v.last_name
    FROM land_plots lp
    LEFT JOIN villagers v ON lp.villager_id = v.villager_id
    ORDER BY lp.plot_code
""")
all_plots = cur.fetchall()

missing_data = []
for p in all_plots:
    issues = []
    if not p['latitude'] or not p['longitude']:
        issues.append('ไม่มีพิกัด (lat/lng)')
    if not p['ptype']:
        issues.append('ไม่มีประเภทการใช้ประโยชน์ (PTYPE)')
    if not p['occupation_since']:
        issues.append('ไม่มีปีที่เข้าทำประโยชน์ (YEAR)')
    if (p['area_rai'] or 0) == 0 and (p['area_ngan'] or 0) == 0 and (p['area_sqwa'] or 0) == 0:
        issues.append('ไม่มีข้อมูลเนื้อที่ (ไร่/งาน/ตร.ว.)')
    if not p['num_apar']:
        issues.append('ไม่มี NUM_APAR')
    if issues:
        missing_data.append((p, issues))

rpt(f"\n{'─'*70}")
rpt(f"  4. แปลงที่ขาดข้อมูลสำคัญ ({len(missing_data)} แปลง)")
rpt(f"{'─'*70}")
for i, (p, issues) in enumerate(missing_data, 1):
    rpt(f"  {i:3d}. [{p['plot_code']}] NUM_APAR={p['num_apar'] or '-'}")
    rpt(f"       เจ้าของ: {p['id_card_number']} {p['first_name']} {p['last_name']}")
    for iss in issues:
        rpt(f"       📋 {iss}")

# ============================================================
# 5. สรุปรวม
# ============================================================
rpt(f"\n{'='*70}")
rpt(f"  สรุปรวม")
rpt(f"{'='*70}")
rpt(f"  ข้อมูลทั้งหมดใน DB:")
rpt(f"    ราษฎร (villagers):    {len(all_villagers)} คน")
rpt(f"    แปลงที่ดิน (plots):   {len(all_plots)} แปลง")
rpt(f"")
rpt(f"  รายการที่ต้องตรวจสอบ:")
rpt(f"    1. แปลงมี data_issues:        {len(di_rows)} แปลง")
rpt(f"    2. เลขบัตรไม่ถูกต้อง:          {len(bad_id_rows)} คน")
rpt(f"    3. ชื่อ/สกุลผิดปกติ:            {len(bad_name_rows)} คน")
rpt(f"    4. แปลงขาดข้อมูลสำคัญ:       {len(missing_data)} แปลง")

# Unique items to check
all_idcards_to_check = set()
for v, _ in bad_id_rows:
    all_idcards_to_check.add(v['id_card_number'])
for v, _ in bad_name_rows:
    all_idcards_to_check.add(v['id_card_number'])
rpt(f"\n  จำนวนราษฎรที่ต้องตรวจ (ไม่ซ้ำ): {len(all_idcards_to_check)} คน")
rpt(f"{'='*70}")

# ============================================================
# Write report
# ============================================================
cur.close()
conn.close()

with open(REPORT_PATH, 'w', encoding='utf-8') as f:
    f.write('\n'.join(report))

print(f"Report saved: {REPORT_PATH}")
print(f"Total lines: {len(report)}")
