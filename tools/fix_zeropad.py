"""Fix spar_no and num_spar: zero-pad to 5 digits"""
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
    charset='utf8mb4', autocommit=False, connect_timeout=10
)
cur = conn.cursor()

print("=== Fix zero-padding: spar_no, num_spar ===\n")

# Before
cur.execute("SELECT spar_no, COUNT(*) FROM land_plots WHERE spar_no IS NOT NULL AND LENGTH(spar_no) < 5 GROUP BY spar_no ORDER BY spar_no")
short_spar = cur.fetchall()
print(f"spar_no < 5 digits: {sum(r[1] for r in short_spar)} records")

cur.execute("SELECT num_spar, COUNT(*) FROM land_plots WHERE num_spar IS NOT NULL AND LENGTH(num_spar) < 5 GROUP BY num_spar ORDER BY num_spar")
short_numspar = cur.fetchall()
print(f"num_spar < 5 digits: {sum(r[1] for r in short_numspar)} records")

# Fix
cur.execute("UPDATE land_plots SET spar_no = LPAD(spar_no, 5, '0') WHERE spar_no IS NOT NULL AND LENGTH(spar_no) < 5")
print(f"\nspar_no updated: {cur.rowcount}")

cur.execute("UPDATE land_plots SET num_spar = LPAD(num_spar, 5, '0') WHERE num_spar IS NOT NULL AND LENGTH(num_spar) < 5")
print(f"num_spar updated: {cur.rowcount}")

conn.commit()

# After - verify
print("\n--- spar_no sample (after fix) ---")
cur.execute("SELECT spar_no, COUNT(*) FROM land_plots WHERE spar_no IS NOT NULL GROUP BY spar_no ORDER BY spar_no LIMIT 15")
for r in cur.fetchall():
    print(f"  {r[0]}  ({r[1]} records)")

print("\n--- num_spar sample (after fix) ---")
cur.execute("SELECT num_spar, COUNT(*) FROM land_plots WHERE num_spar IS NOT NULL GROUP BY num_spar ORDER BY num_spar LIMIT 15")
for r in cur.fetchall():
    print(f"  {r[0]}  ({r[1]} records)")

print("\n✅ Done!")
cur.close()
conn.close()
