"""Check for duplicate rows in land_plots (same villager + same data, different plot_code)"""
import pymysql
from urllib.parse import urlparse
import os

ENV_PATH = r"c:\Users\Administrator\OneDrive\000_Ai Project\PHP_SQL\.env"
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
cur = conn.cursor()

# 1. Check specific IDs from screenshot
print("=== Specific records from screenshot ===\n")
for idc in ['5100600006581', '3710400098693', '3711000172358']:
    cur.execute("""
        SELECT lp.plot_id, lp.plot_code, lp.villager_id, lp.num_apar, 
               lp.spar_no, lp.num_spar, lp.apar_no, lp.area_rai
        FROM land_plots lp
        JOIN villagers v ON lp.villager_id = v.villager_id
        WHERE v.id_card_number = %s
        ORDER BY lp.plot_id
    """, (idc,))
    rows = cur.fetchall()
    print(f"ID: {idc} -> {len(rows)} plots")
    for r in rows:
        print(f"  plot_id={r[0]} code={r[1]} num_apar={r[3]} spar_no={r[4]} num_spar={r[5]} apar_no={r[6]} rai={r[7]}")
    print()

# 2. Find ALL duplicates: same villager_id + num_apar + spar_no + num_spar
print("=== All duplicate groups (same villager + num_apar + spar_no + num_spar) ===\n")
cur.execute("""
    SELECT villager_id, num_apar, spar_no, num_spar, COUNT(*) as cnt
    FROM land_plots
    GROUP BY villager_id, num_apar, spar_no, num_spar
    HAVING cnt > 1
    ORDER BY cnt DESC
""")
dup_groups = cur.fetchall()
print(f"Total duplicate groups: {len(dup_groups)}")
total_extra = sum(r[4] - 1 for r in dup_groups)
print(f"Total extra rows to remove: {total_extra}\n")

for g in dup_groups[:20]:
    vid, numa, sparno, numspar, cnt = g
    cur.execute("""
        SELECT lp.plot_id, lp.plot_code, v.first_name, v.last_name
        FROM land_plots lp
        JOIN villagers v ON lp.villager_id = v.villager_id
        WHERE lp.villager_id = %s AND lp.num_apar = %s 
              AND lp.spar_no = %s AND lp.num_spar = %s
        ORDER BY lp.plot_id
    """, (vid, numa, sparno, numspar))
    details = cur.fetchall()
    print(f"villager_id={vid} num_apar={numa} spar={sparno} numspar={numspar} -> {cnt}x")
    for d in details:
        print(f"  plot_id={d[0]} code={d[1]} name={d[2]} {d[3]}")
    print()

if len(dup_groups) > 20:
    print(f"... and {len(dup_groups) - 20} more groups")

cur.close()
conn.close()
