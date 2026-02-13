"""Analyze DUP plot_codes to understand the root cause"""
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

print("=== 1. DUP plots: plot_code vs spar_code vs num_apar ===\n")
cur.execute("""
    SELECT plot_code, spar_code, num_apar, apar_no, apar_code
    FROM land_plots
    WHERE plot_code LIKE '%\\_DUP%' ESCAPE '\\\\'
    ORDER BY spar_code, num_apar
    LIMIT 20
""")
for r in cur.fetchall():
    print(f"  plot_code={r[0]}  spar_code={r[1]}  num_apar={r[2]}  apar_no={r[3]}  apar_code={r[4]}")

print("\n=== 2. Example: show ORIGINAL + DUP for same spar_code ===\n")
cur.execute("""
    SELECT spar_code FROM land_plots
    WHERE plot_code LIKE '%\\_DUP%' ESCAPE '\\\\'
    LIMIT 3
""")
dup_spars = [r[0] for r in cur.fetchall()]

for sc in dup_spars:
    print(f"  --- SPAR_CODE: {sc} ---")
    cur.execute("""
        SELECT plot_code, num_apar, apar_no, apar_code
        FROM land_plots WHERE spar_code = %s ORDER BY num_apar
    """, (sc,))
    for r in cur.fetchall():
        dup_mark = " <-- DUP" if "_DUP" in r[0] else ""
        print(f"    plot_code={r[0]}  num_apar={r[1]}  apar_no={r[2]}  apar_code={r[3]}{dup_mark}")
    print()

print("=== 3. Count: how many spar_codes have multiple plots? ===\n")
cur.execute("""
    SELECT spar_code, COUNT(*) as cnt
    FROM land_plots
    WHERE spar_code IS NOT NULL AND spar_code != ''
    GROUP BY spar_code HAVING cnt > 1
    ORDER BY cnt DESC LIMIT 10
""")
for r in cur.fetchall():
    print(f"  spar_code={r[0]}  plots={r[1]}")

cur.execute("""
    SELECT COUNT(DISTINCT spar_code) FROM land_plots
    WHERE spar_code IS NOT NULL AND spar_code != ''
""")
print(f"\n  Unique SPAR_CODEs: {cur.fetchone()[0]}")
cur.execute("SELECT COUNT(*) FROM land_plots")
print(f"  Total plots: {cur.fetchone()[0]}")
cur.execute("""
    SELECT COUNT(*) FROM (
        SELECT spar_code FROM land_plots
        WHERE spar_code IS NOT NULL AND spar_code != ''
        GROUP BY spar_code HAVING COUNT(*) > 1
    ) t
""")
print(f"  SPAR_CODEs with >1 plot: {cur.fetchone()[0]}")

print("\n=== 4. Could NUM_APAR + APAR_CODE be unique? ===\n")
cur.execute("""
    SELECT CONCAT(COALESCE(apar_code,''), '-', COALESCE(num_apar,'')) as combo, COUNT(*) as cnt
    FROM land_plots GROUP BY combo HAVING cnt > 1
    ORDER BY cnt DESC LIMIT 10
""")
rows = cur.fetchall()
if rows:
    print("  Duplicates found:")
    for r in rows:
        print(f"    {r[0]} = {r[1]} plots")
else:
    print("  No duplicates! APAR_CODE + NUM_APAR is unique!")

cur.close()
conn.close()
