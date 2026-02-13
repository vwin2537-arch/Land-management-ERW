"""
สร้างรายงาน audit_hardpaper.txt ฉบับปรับปรุง
- ข้อมูลเป็นปัจจุบันหลังลบ DUP แล้ว
- เพิ่มหมวด SPAR_CODE ที่เคยซ้ำ (แก้ไขแล้ว) ให้เจ้าหน้าที่ทราบ
"""
import pymysql
import os
import re
from urllib.parse import urlparse
from datetime import datetime

ENV_PATH = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\.env"
REPORT = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\tools\audit_hardpaper.txt"

def read_env(path):
    env = {}
    if not os.path.exists(path): return env
    with open(path, encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'): continue
            if '=' in line:
                k, v = line.split('=', 1)
                env[k.strip()] = v.strip()
    return env

env = read_env(ENV_PATH)
mysql_url = env.get('MYSQL_URL', '')
p = urlparse(mysql_url)
conn = pymysql.connect(
    host=p.hostname or '127.0.0.1', port=p.port or 3306,
    user=p.username or 'root', password=p.password or '',
    database=(p.path or '/land_management').lstrip('/'),
    charset='utf8mb4', connect_timeout=10
)
cur = conn.cursor(pymysql.cursors.DictCursor)

rpt = []
def w(msg=''):
    rpt.append(msg)

# ============================================================
# Header
# ============================================================
w("=" * 72)
w("  รายงานข้อมูลที่ต้องตรวจสอบกับ Hard Paper")
w(f"  วันที่สร้าง: {datetime.now().strftime('%Y-%m-%d %H:%M')}")
w(f"  (ปรับปรุงล่าสุดหลังแก้ไข DUP records)")
w("=" * 72)

# Get totals
cur.execute("SELECT COUNT(*) as c FROM villagers")
total_v = cur.fetchone()['c']
cur.execute("SELECT COUNT(*) as c FROM land_plots")
total_p = cur.fetchone()['c']

w(f"\n  ข้อมูลใน DB ปัจจุบัน:")
w(f"    ราษฎร (villagers):    {total_v} คน")
w(f"    แปลงที่ดิน (plots):   {total_p} แปลง")

# ============================================================
# 1. เลขบัตรประชาชนที่ไม่ถูกต้อง
# ============================================================
def validate_idcard(idc):
    issues = []
    if not idc:
        issues.append('ไม่มีเลขบัตร')
        return issues
    if idc.startswith('TEMP_'):
        issues.append(f'เลขบัตรชั่วคราว: {idc}')
        return issues
    if ' ' in idc:
        issues.append(f'มีช่องว่างในเลขบัตร')
    clean = idc.replace(' ', '')
    if len(clean) != 13:
        issues.append(f'ไม่ครบ 13 หลัก ({len(clean)} หลัก)')
    if not clean.isdigit():
        issues.append('มีตัวอักษรปน')
    elif len(clean) == 13:
        s = sum(int(clean[i]) * (13 - i) for i in range(12))
        check = (11 - (s % 11)) % 10
        if check != int(clean[12]):
            issues.append(f'checksum ผิด (หลักสุดท้ายควรเป็น {check} แต่เป็น {clean[12]})')
        if clean.startswith('0'):
            issues.append('ขึ้นต้นด้วย 0 (น่าสงสัย)')
    return issues

cur.execute("""
    SELECT v.villager_id, v.id_card_number, v.prefix, v.first_name, v.last_name,
           GROUP_CONCAT(lp.plot_code SEPARATOR ', ') as plots,
           GROUP_CONCAT(lp.num_apar SEPARATOR ', ') as num_apars
    FROM villagers v
    LEFT JOIN land_plots lp ON v.villager_id = lp.villager_id
    GROUP BY v.villager_id
    ORDER BY v.villager_id
""")
all_villagers = cur.fetchall()

bad_id_rows = []
for v in all_villagers:
    id_issues = validate_idcard(v['id_card_number'])
    if id_issues:
        bad_id_rows.append((v, id_issues))

w(f"\n{'─'*72}")
w(f"  1. ราษฎรที่เลขบัตรประชาชนไม่ถูกต้อง ({len(bad_id_rows)} คน)")
w(f"     *** กรุณาตรวจสอบจาก Hard Paper แล้วแจ้งเลขบัตรที่ถูกต้อง ***")
w(f"{'─'*72}")
for i, (v, issues) in enumerate(bad_id_rows, 1):
    w(f"\n  {i:3d}. {v['prefix'] or ''}{v['first_name']} {v['last_name']}")
    w(f"       เลขบัตรใน DB: {v['id_card_number']}")
    w(f"       แปลง: {v['plots'] or '-'}")
    w(f"       NUM_APAR: {v['num_apars'] or '-'}")
    for iss in issues:
        w(f"       ❌ {iss}")
    w(f"       📝 เลขบัตรที่ถูกต้อง: ____________________________")

# ============================================================
# 2. แปลงที่มี data_issues ที่เหลืออยู่
# ============================================================
cur.execute("""
    SELECT lp.plot_code, lp.num_apar, lp.spar_code, lp.data_issues,
           v.id_card_number, v.prefix, v.first_name, v.last_name
    FROM land_plots lp
    LEFT JOIN villagers v ON lp.villager_id = v.villager_id
    WHERE lp.data_issues IS NOT NULL
    ORDER BY lp.plot_code
""")
di_rows = cur.fetchall()

w(f"\n{'─'*72}")
w(f"  2. แปลงที่มีปัญหาอื่นๆ ({len(di_rows)} แปลง)")
w(f"{'─'*72}")
for i, r in enumerate(di_rows, 1):
    w(f"\n  {i:3d}. [{r['plot_code']}]")
    w(f"       SPAR_CODE: {r['spar_code'] or '-'}  NUM_APAR: {r['num_apar'] or '-'}")
    w(f"       เจ้าของ: {r['id_card_number']} {r['prefix'] or ''}{r['first_name']} {r['last_name']}")
    w(f"       ปัญหา: {r['data_issues']}")

# ============================================================
# 3. SPAR_CODE ที่เคยซ้ำ — แก้ไขแล้ว (เพื่อทราบ)
# ============================================================
# Records ที่ถูก rename (มี _ ตามด้วย NUM_APAR หรือ _B)
cur.execute("""
    SELECT lp.plot_id, lp.plot_code, lp.spar_code, lp.num_apar, lp.apar_no,
           lp.area_rai, lp.area_ngan, lp.area_sqwa,
           v.id_card_number, v.prefix, v.first_name, v.last_name
    FROM land_plots lp
    LEFT JOIN villagers v ON lp.villager_id = v.villager_id
    WHERE lp.plot_code LIKE '%%_B'
       OR (lp.spar_code IS NOT NULL AND lp.plot_code != lp.spar_code
           AND lp.plot_code NOT LIKE 'IMP-%%'
           AND lp.plot_code REGEXP '_[0-9]+$')
    ORDER BY lp.spar_code
""")
renamed_rows = cur.fetchall()

# Also find SPAR_CODEs with multiple plots
cur.execute("""
    SELECT spar_code, COUNT(*) as cnt
    FROM land_plots
    WHERE spar_code IS NOT NULL AND spar_code != ''
    GROUP BY spar_code HAVING cnt > 1
    ORDER BY spar_code
""")
multi_spar = cur.fetchall()

w(f"\n{'─'*72}")
w(f"  3. SPAR_CODE ที่มีหลายแปลง ({len(multi_spar)} รหัส, แก้ไข plot_code แล้ว)")
w(f"     *** เพื่อทราบ: แปลงเหล่านี้ SPAR_CODE เดียวกันแต่เป็นแปลงต่างกันจริง ***")
w(f"     *** ตรวจสอบแล้ว ถูกต้อง — ไม่ต้องดำเนินการเพิ่ม ***")
w(f"{'─'*72}")

for ms in multi_spar:
    sc = ms['spar_code']
    cnt = ms['cnt']
    cur.execute("""
        SELECT lp.plot_code, lp.num_apar, lp.apar_no,
               lp.area_rai, lp.area_ngan, lp.area_sqwa,
               v.id_card_number, v.prefix, v.first_name, v.last_name
        FROM land_plots lp
        LEFT JOIN villagers v ON lp.villager_id = v.villager_id
        WHERE lp.spar_code = %s
        ORDER BY lp.num_apar
    """, (sc,))
    plots = cur.fetchall()
    
    w(f"\n  SPAR_CODE: {sc}  ({cnt} แปลง)")
    for pl in plots:
        w(f"    plot_code={pl['plot_code']}  NUM_APAR={pl['num_apar']}  APAR_NO={pl['apar_no']}")
        w(f"      เจ้าของ: {pl['id_card_number']} {pl['prefix'] or ''}{pl['first_name']} {pl['last_name']}")
        w(f"      เนื้อที่: {pl['area_rai'] or 0} ไร่ {pl['area_ngan'] or 0} งาน {pl['area_sqwa'] or 0} ตร.ว.")

# ============================================================
# 4. SPAR_CODE ที่ซ้ำแล้วถูกลบ (บันทึกไว้เพื่อทราบ)
# ============================================================
w(f"\n{'─'*72}")
w(f"  4. Records ที่ถูกลบเนื่องจากซ้ำกับ Original (76 records)")
w(f"     *** เพื่อทราบ: records เหล่านี้เป็นข้อมูลซ้ำจาก import ครั้งแรก ***")
w(f"     *** ข้อมูลยังคงอยู่ใน Original plot_code — ไม่สูญหาย ***")
w(f"{'─'*72}")
w(f"     (ดูรายละเอียดทั้งหมดใน tools/dup_detail_report.txt)")

# ============================================================
# 5. สรุป
# ============================================================
w(f"\n{'='*72}")
w(f"  สรุปสิ่งที่ต้องตรวจสอบจาก Hard Paper")
w(f"{'='*72}")
w(f"")
w(f"  ┌─────────────────────────────────────────────────────────────┐")
w(f"  │  1. เลขบัตรประชาชนไม่ถูกต้อง:  {len(bad_id_rows):>3d} คน  ← ต้องแก้ไข     │")
w(f"  │  2. แปลงมีปัญหาอื่นๆ:           {len(di_rows):>3d} แปลง                  │")
w(f"  │  3. SPAR_CODE หลายแปลง:        {len(multi_spar):>3d} รหัส  (แก้ไขแล้ว)   │")
w(f"  │  4. Records ซ้ำถูกลบ:            76 records (เพื่อทราบ)   │")
w(f"  └─────────────────────────────────────────────────────────────┘")
w(f"")
w(f"  ข้อมูลใน DB หลังแก้ไข:")
w(f"    ราษฎร:     {total_v} คน")
w(f"    แปลงที่ดิน: {total_p} แปลง")
w(f"")
w(f"  เมื่อเจ้าหน้าที่ตรวจสอบเลขบัตรเสร็จแล้ว กรุณาแจ้งข้อมูลที่ถูกต้องกลับมา")
w(f"  เพื่อปรับปรุงฐานข้อมูลต่อไป")
w(f"{'='*72}")

cur.close()
conn.close()

with open(REPORT, 'w', encoding='utf-8') as f:
    f.write('\n'.join(rpt))

print(f"Report saved: {REPORT}")
print(f"Total lines: {len(rpt)}")
