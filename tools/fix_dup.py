"""
แก้ไข DUP plots ใน DB:
- ลบ records ที่ซ้ำจริง (SPAR_CODE + NUM_APAR เหมือน original)
- เปลี่ยน plot_code ของ records ที่เป็นแปลงต่างกันจริง
"""
import pymysql
import os
from urllib.parse import urlparse

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
cur = conn.cursor(pymysql.cursors.DictCursor)

print("=== Fix DUP plots ===\n")

# Count before
cur.execute("SELECT COUNT(*) as c FROM land_plots")
before_plots = cur.fetchone()['c']
cur.execute("SELECT COUNT(*) as c FROM land_plots WHERE plot_code LIKE '%%_DUP%%'")
before_dup = cur.fetchone()['c']
print(f"Before: {before_plots} plots, {before_dup} DUP records")

# Get all DUP plots
cur.execute("""
    SELECT plot_id, plot_code, spar_code, num_apar
    FROM land_plots WHERE plot_code LIKE '%%_DUP%%'
    ORDER BY spar_code, num_apar
""")
dup_plots = cur.fetchall()

deleted = 0
renamed = 0

# 3 geo-different pairs (same SPAR+NUM but different polygon in .shp)
geo_diff_spars = {
    ('BKR10120000700002', '10002'),
    ('BKR10120000700003', '10003'),
    ('BKR10120000700004', '10004'),
}

try:
    for dp in dup_plots:
        spar = dp['spar_code']
        num = dp['num_apar']
        pid = dp['plot_id']
        pc = dp['plot_code']

        # Find original (same spar_code, no _DUP)
        cur.execute("""
            SELECT plot_id, num_apar FROM land_plots
            WHERE spar_code = %s AND plot_code NOT LIKE '%%_DUP%%'
            LIMIT 1
        """, (spar,))
        orig = cur.fetchone()

        if (spar, num) in geo_diff_spars:
            # Group A2: same SPAR+NUM but different geometry -> rename with _B suffix
            new_code = f"{spar}_{num}_B"
            cur.execute("UPDATE land_plots SET plot_code = %s, data_issues = NULL WHERE plot_id = %s", (new_code, pid))
            print(f"  RENAME (geo-diff): {pc} -> {new_code}")
            renamed += 1
        elif orig and orig['num_apar'] == num:
            # Group A1: true duplicate -> delete
            cur.execute("DELETE FROM land_plots WHERE plot_id = %s", (pid,))
            print(f"  DELETE: {pc} (dup of original)")
            deleted += 1
        else:
            # Group B: different NUM_APAR -> rename with NUM_APAR suffix
            new_code = f"{spar}_{num}"
            cur.execute("UPDATE land_plots SET plot_code = %s, data_issues = NULL WHERE plot_id = %s", (new_code, pid))
            print(f"  RENAME (diff-num): {pc} -> {new_code}")
            renamed += 1

    conn.commit()

    # Count after
    cur.execute("SELECT COUNT(*) as c FROM land_plots")
    after_plots = cur.fetchone()['c']
    cur.execute("SELECT COUNT(*) as c FROM land_plots WHERE plot_code LIKE '%%_DUP%%'")
    after_dup = cur.fetchone()['c']
    cur.execute("SELECT COUNT(*) as c FROM land_plots WHERE data_issues IS NOT NULL")
    after_issues = cur.fetchone()['c']

    print(f"\n{'='*50}")
    print(f"✅ Done!")
    print(f"  Deleted:  {deleted}")
    print(f"  Renamed:  {renamed}")
    print(f"  Before:   {before_plots} plots ({before_dup} DUP)")
    print(f"  After:    {after_plots} plots ({after_dup} DUP remaining)")
    print(f"  data_issues remaining: {after_issues}")
    print(f"{'='*50}")

except Exception as ex:
    conn.rollback()
    print(f"\n❌ Error: {ex}")
    import traceback
    traceback.print_exc()
finally:
    cur.close()
    conn.close()
