"""
รายงานรายละเอียด DUP plots
แยกเป็น 2 กลุ่ม:
  A) ซ้ำจริง (SPAR_CODE + NUM_APAR เหมือนกัน) → ควรลบ DUP ทิ้ง
  B) แปลงต่างกัน (SPAR_CODE เดียวกัน แต่ NUM_APAR ต่างกัน) → ควรเปลี่ยน plot_code
"""
import pymysql
import os
from urllib.parse import urlparse

ENV_PATH = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\.env"
REPORT = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\tools\dup_detail_report.txt"

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

# Get all DUP plots
cur.execute("""
    SELECT lp.plot_id, lp.plot_code, lp.spar_code, lp.num_apar, lp.apar_no,
           lp.area_rai, lp.area_ngan, lp.area_sqwa, lp.ptype,
           v.id_card_number, v.prefix, v.first_name, v.last_name
    FROM land_plots lp
    LEFT JOIN villagers v ON lp.villager_id = v.villager_id
    WHERE lp.plot_code LIKE '%%_DUP%%'
    ORDER BY lp.spar_code, lp.num_apar
""")
dup_plots = cur.fetchall()

# For each DUP, find its original (same spar_code, no _DUP)
group_a = []  # true duplicates
group_b = []  # different plots

for dp in dup_plots:
    spar = dp['spar_code']
    num = dp['num_apar']

    # Find original
    cur.execute("""
        SELECT lp.plot_id, lp.plot_code, lp.num_apar, lp.apar_no,
               lp.area_rai, lp.area_ngan, lp.area_sqwa, lp.ptype,
               v.id_card_number, v.first_name, v.last_name
        FROM land_plots lp
        LEFT JOIN villagers v ON lp.villager_id = v.villager_id
        WHERE lp.spar_code = %s AND lp.plot_code NOT LIKE '%%_DUP%%'
        LIMIT 1
    """, (spar,))
    orig = cur.fetchone()

    if orig and orig['num_apar'] == num:
        # Same NUM_APAR = true duplicate
        group_a.append({'dup': dp, 'orig': orig})
    else:
        # Different NUM_APAR = different plot
        group_b.append({'dup': dp, 'orig': orig})

# Build report
lines = []
def rpt(msg=''):
    lines.append(msg)

rpt("=" * 72)
rpt("  รายงานรายละเอียด DUP plots")
rpt(f"  วันที่: {__import__('datetime').datetime.now().strftime('%Y-%m-%d %H:%M')}")
rpt("=" * 72)
rpt(f"\n  DUP plots ทั้งหมด: {len(dup_plots)}")
rpt(f"  กลุ่ม A (ซ้ำจริง → ลบ DUP):         {len(group_a)}")
rpt(f"  กลุ่ม B (แปลงต่างกัน → แก้ plot_code): {len(group_b)}")

# ─── Group A ───
rpt(f"\n{'─'*72}")
rpt(f"  กลุ่ม A: ซ้ำจริง — SPAR_CODE + NUM_APAR เหมือนกันทุกประการ")
rpt(f"  แนะนำ: ลบ DUP ออก เพราะเป็น record เดียวกับ original")
rpt(f"  จำนวน: {len(group_a)} แปลง")
rpt(f"{'─'*72}")

for i, item in enumerate(group_a, 1):
    dp = item['dup']
    og = item['orig']
    rpt(f"\n  {i:3d}. ❌ ลบ: plot_id={dp['plot_id']}  plot_code={dp['plot_code']}")
    rpt(f"       NUM_APAR={dp['num_apar']}  APAR_NO={dp['apar_no']}")
    rpt(f"       เจ้าของ: {dp['id_card_number']} {dp['prefix'] or ''}{dp['first_name']} {dp['last_name']}")
    rpt(f"       เนื้อที่: {dp['area_rai'] or 0} ไร่ {dp['area_ngan'] or 0} งาน {dp['area_sqwa'] or 0} ตร.ว.")
    rpt(f"       ─ ตัวจริง: plot_id={og['plot_id']}  plot_code={og['plot_code']}")
    rpt(f"         NUM_APAR={og['num_apar']}  APAR_NO={og['apar_no']}")
    rpt(f"         เจ้าของ: {og['id_card_number']} {og['first_name']} {og['last_name']}")
    rpt(f"         เนื้อที่: {og['area_rai'] or 0} ไร่ {og['area_ngan'] or 0} งาน {og['area_sqwa'] or 0} ตร.ว.")

# ─── Group B ───
rpt(f"\n{'─'*72}")
rpt(f"  กลุ่ม B: แปลงต่างกัน — SPAR_CODE เดียวกัน แต่ NUM_APAR ต่างกัน")
rpt(f"  แนะนำ: เปลี่ยน plot_code จาก '..._DUP##' เป็น 'SPAR_CODE_NUMAPAR'")
rpt(f"  จำนวน: {len(group_b)} แปลง")
rpt(f"{'─'*72}")

for i, item in enumerate(group_b, 1):
    dp = item['dup']
    og = item['orig']
    new_code = f"{dp['spar_code']}_{dp['num_apar']}" if dp['spar_code'] and dp['num_apar'] else dp['plot_code']
    rpt(f"\n  {i:3d}. 🔄 เปลี่ยน: plot_id={dp['plot_id']}")
    rpt(f"       เดิม:  plot_code={dp['plot_code']}")
    rpt(f"       ใหม่:  plot_code={new_code}")
    rpt(f"       NUM_APAR={dp['num_apar']}  APAR_NO={dp['apar_no']}")
    rpt(f"       เจ้าของ: {dp['id_card_number']} {dp['prefix'] or ''}{dp['first_name']} {dp['last_name']}")
    rpt(f"       เนื้อที่: {dp['area_rai'] or 0} ไร่ {dp['area_ngan'] or 0} งาน {dp['area_sqwa'] or 0} ตร.ว.")
    if og:
        rpt(f"       ─ original: plot_code={og['plot_code']}  NUM_APAR={og['num_apar']}")
        rpt(f"         เจ้าของ: {og['id_card_number']} {og['first_name']} {og['last_name']}")

# ─── SQL Preview ───
rpt(f"\n{'='*72}")
rpt(f"  SQL ที่จะรัน (preview)")
rpt(f"{'='*72}")

rpt(f"\n  -- กลุ่ม A: ลบ {len(group_a)} records ที่ซ้ำ")
for item in group_a:
    rpt(f"  DELETE FROM land_plots WHERE plot_id = {item['dup']['plot_id']};  -- {item['dup']['plot_code']}")

rpt(f"\n  -- กลุ่ม B: เปลี่ยน plot_code {len(group_b)} records")
for item in group_b:
    dp = item['dup']
    new_code = f"{dp['spar_code']}_{dp['num_apar']}" if dp['spar_code'] and dp['num_apar'] else dp['plot_code']
    rpt(f"  UPDATE land_plots SET plot_code = '{new_code}', data_issues = NULL WHERE plot_id = {dp['plot_id']};")

rpt(f"\n{'='*72}")

cur.close()
conn.close()

with open(REPORT, 'w', encoding='utf-8') as f:
    f.write('\n'.join(lines))

print(f"Report saved: {REPORT}")
print(f"Group A (delete): {len(group_a)}")
print(f"Group B (rename): {len(group_b)}")
print(f"Total lines: {len(lines)}")
