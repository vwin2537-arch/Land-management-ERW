# SETUP GUIDE — ระบบจัดการที่ดินทำกิน (LandMS)

> **ไฟล์นี้สำหรับ AI หรือ Developer อ่านไฟล์เดียวแล้วเซ็ตอัพโปรเจคได้ทันที**
> อัพเดทล่าสุด: 2026-02-14 (v5 — เพิ่มขอบเขตอุทยานฯ + Unified Map Layers)

---

## 1. ข้อมูลโปรเจค

- **ชื่อ**: ระบบจัดการที่ดินทำกินในเขตอุทยานแห่งชาติ
- **Tech Stack**: PHP 8.x (vanilla, ไม่มี framework) + MySQL/MariaDB + HTML/CSS/JS
- **เซิร์ฟเวอร์**: XAMPP (Windows) หรือ Railway (Production)
- **PHP CLI**: `C:\xampp\php\php.exe` (ถ้าไม่อยู่ใน PATH ต้องใช้ full path)
- **MySQL CLI**: `C:\xampp\mysql\bin\mysql.exe`
- **Database**: `land_management` (charset: `utf8mb4`, collation: `utf8mb4_thai_520_w2`)
- **Login เริ่มต้น**: username `admin` / password `admin123`

---

## 2. โครงสร้างโฟลเดอร์

```
PHP_SQL/
├── index.php              ← Entry point + Router
├── config/
│   ├── database.php       ← DB connection (PDO) — อ่าน .env หรือ env vars
│   └── constants.php      ← App constants, roles, labels, CSRF helpers
├── controllers/
│   ├── AuthController.php
│   ├── VillagerController.php
│   ├── PlotController.php
│   ├── CaseController.php
│   ├── UserController.php
│   ├── DocumentController.php
│   ├── ReportController.php
│   ├── FormExportController.php    ← ดึงข้อมูลสำหรับฟอร์มราชการ (อส.6-1, 6-2, ...)
│   ├── VerificationController.php  ← ตรวจสอบสิทธิ์ + จัดสรรที่ดิน + สร้างแปลง prefix 3/4
│   └── SubdivisionController.php   ← Dashboard สรุปสถานะแบ่งแปลง (read-only)
├── models/
│   ├── Plot.php
│   ├── Villager.php
│   └── ...
├── views/
│   ├── layout/header.php, footer.php  ← Sidebar + Topbar
│   ├── dashboard/
│   ├── villagers/
│   ├── plots/
│   ├── cases/
│   ├── map/
│   ├── verification/       ← ตรวจสอบสิทธิ์ + จัดสรร (สร้างแปลง prefix 3/4 อัตโนมัติ)
│   ├── subdivision/         ← Dashboard สรุปสถานะแบ่งแปลง
│   ├── forms/               ← ฟอร์มราชการ (อส.6-1, 6-2, 6-3, บช.11, บช.12, หนังสือรับรอง)
│   ├── reports/
│   ├── users/
│   └── villages/
├── assets/
│   ├── css/style.css        ← Main CSS
│   ├── css/form-print.css   ← CSS สำหรับพิมพ์ฟอร์มราชการ (table-layout: fixed)
│   └── js/map-popup.js      ← Shared map functions: plotPopupHtml() + addMapLayers()
├── tools/
│   ├── import_shapefile.php   ← Import จาก .dbf (Shapefile) เข้า villagers + land_plots
│   ├── inspect_xlsx.py        ← ตรวจสอบความถูกต้อง Excel (เลขบัตร, ชื่อ, artifact)
│   ├── fix_xlsx.py            ← แก้ไข Excel (_x000D_ artifact, HOME_NO date format)
│   ├── upsert_xlsx.py         ← UPSERT ข้อมูลจาก Excel เข้า DB (villagers + land_plots)
│   ├── audit_report.py        ← สร้างรายงานตรวจสอบข้อมูล (audit_hardpaper.txt)
│   ├── fix_dup.py             ← ลบ/แก้ไข DUP records ที่ SPAR_CODE ซ้ำ
│   ├── check_shp_dup.py       ← เปรียบเทียบ geometry ใน .shp กับ DUP records
│   └── audit_hardpaper.txt    ← รายงานข้อมูลที่ต้องตรวจกับ Hard Paper (ส่งเจ้าหน้าที่)
├── sql/
│   ├── schema.sql                    ← โครงสร้าง DB เริ่มต้น (8 ตาราง)
│   ├── full_backup_railway.sql       ← Backup ข้อมูลจริงทั้งหมด (import ทับ schema ได้)
│   ├── migration_forms.sql           ← เพิ่ม columns สำหรับฟอร์ม (watershed_class, remark_risk)
│   ├── migration_subdivision.sql     ← สร้าง plot_allocations + parent_plot_id, allocation_type
│   └── migration_verification_cols.sql ← เพิ่ม verification_status, verified_at, verified_by ใน villagers
├── ตรวจสอบคุณสมบัติ/                  ← ข้อมูลต้นทาง (Shapefile + Excel)
│   ├── Merge_แปลงสอบทาน.shp/dbf/shx  ← Shapefile แปลงสำรวจ (.shp เก่า ยังไม่ปรับปรุง)
│   ├── ตารางแปลงสอบทาน2.xlsx         ← ข้อมูลล่าสุดจากเจ้าหน้าที่ (แก้ไขแล้ว)
│   └── ตารางแปลงสอบทาน2_backup.xlsx  ← Backup ก่อนแก้ไข
├── run_migration.php       ← ตัวรัน SQL migration files (ใช้ mysqli multi_query)
├── setup.php               ← Web-based DB setup (สำหรับ Railway deploy)
├── composer.json            ← Dependencies (แค่ ext-pdo_mysql)
├── Dockerfile               ← สำหรับ Railway deploy
├── data/
│   ├── erawan_boundary.geojson  ← ขอบเขตอุทยานฯ เอราวัณ (แปลงจาก SHP→GeoJSON)
│   └── plots_boundaries.geojson ← ขอบเขตแปลงที่ดิน (GeoJSON)
├── MAP_ERW/                     ← Shapefile ขอบเขตอุทยานฯ (ต้นฉบับ)
├── convert_shp.js               ← Script แปลง SHP→GeoJSON (Node.js, ใช้ครั้งเดียว)
├── manifest.json                ← PWA manifest
└── sw.js                        ← Service Worker (PWA)
```

---

## 3. การเซ็ตอัพในเครื่องใหม่ (Step-by-step)

### 3.1 ติดตั้ง XAMPP
- ดาวน์โหลด XAMPP จาก https://www.apachefriends.org/
- ติดตั้งแล้วเปิด **Apache** + **MySQL** จาก XAMPP Control Panel

### 3.2 เชื่อมโฟลเดอร์โปรเจค (Symbolic Link)
เปิด **CMD ด้วยสิทธิ์ Administrator** แล้วรัน:
```cmd
mklink /D "C:\xampp\htdocs\PHP_SQL" "C:\Users\<ชื่อ USER>\OneDrive\000_Ai Project\PHP_SQL"
```
> แก้ `<ชื่อ USER>` ให้ตรงกับเครื่อง เช่น `Administrator`, `This PC`

### 3.3 สร้างฐานข้อมูล
```cmd
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE IF NOT EXISTS land_management CHARACTER SET utf8mb4 COLLATE utf8mb4_thai_520_w2;"
```

### 3.4 นำเข้าข้อมูล (เลือกวิธีใดวิธีหนึ่ง)

**วิธี A — Import backup ทั้งหมด (แนะนำ ได้ข้อมูลจริงครบ):**
```cmd
C:\xampp\mysql\bin\mysql -u root land_management < "C:\Users\<ชื่อ USER>\OneDrive\000_Ai Project\PHP_SQL\sql\full_backup_railway.sql"
```

**วิธี B — สร้าง schema เปล่า (ไม่มีข้อมูล):**
```cmd
C:\xampp\mysql\bin\mysql -u root land_management < "...\sql\schema.sql"
```

### 3.5 รัน Migrations (ต้องรันทุกไฟล์ ตามลำดับ)
```cmd
cd "C:\Users\<ชื่อ USER>\OneDrive\000_Ai Project\PHP_SQL"
C:\xampp\php\php.exe run_migration.php sql/migration_forms.sql
C:\xampp\php\php.exe run_migration.php sql/migration_subdivision.sql
C:\xampp\php\php.exe run_migration.php sql/migration_verification_cols.sql
```
> Migration scripts เป็น idempotent (รันซ้ำได้ไม่พัง เพราะเช็ค IF NOT EXISTS / IF column exists)

### 3.6 ทดสอบ
- เปิด http://localhost/PHP_SQL/
- Login: `admin` / `admin123`

---

## 4. การตั้งค่าฐานข้อมูล (Config)

ไฟล์ `config/database.php` อ่านค่าจาก **.env** หรือ **environment variables** ตามลำดับ:
1. อ่านไฟล์ `.env` ที่ root โปรเจค (ถ้ามี)
2. ถ้ามี `MYSQL_URL` (Railway format) จะ parse URL อัตโนมัติ
3. ถ้าไม่มี ใช้ค่า default: `root@127.0.0.1:3306/land_management` (ไม่มี password)

**สร้างไฟล์ .env (optional):**
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=land_management
```

---

## 5. ฐานข้อมูล (MySQL/MariaDB — ไม่ใช่ Excel)

> **หมายเหตุ**: ฐานข้อมูลคือ **MySQL/MariaDB** (`land_management`)
> Excel (.xlsx) เป็นแค่ **ไฟล์ต้นทาง** ที่เจ้าหน้าที่ส่งมา → import เข้า DB ด้วย Python script
> Shapefile (.shp/.dbf) เป็น **ข้อมูลเชิงพื้นที่** ที่ import ครั้งแรกด้วย PHP script

### 5.1 ตาราง (9 ตาราง)

| ตาราง | PK | คำอธิบาย |
|-------|-----|----------|
| `users` | `user_id` | ผู้ใช้งาน/เจ้าหน้าที่ (admin, officer, viewer) |
| `villagers` | `villager_id` | ทะเบียนราษฎร (UNIQUE: `id_card_number`) |
| `land_plots` | `plot_id` | แปลงที่ดินทำกิน (UNIQUE: `plot_code`) |
| `household_members` | `member_id` | สมาชิกครัวเรือน/ทายาท |
| `cases` | `case_id` | คำร้อง/เรื่องร้องเรียน (UNIQUE: `case_number`) |
| `documents` | `doc_id` | เอกสารแนบ (polymorphic: villager/plot/case) |
| `activity_logs` | `log_id` | บันทึกการใช้งาน |
| `report_templates` | `template_id` | เทมเพลตรายงาน |
| `plot_allocations` | `id` | บันทึกการจัดสรรที่ดิน (owner/heir/section19) |

### 5.2 Relation Diagram (ER)

```
                          ┌──────────────────┐
                          │      users       │
                          │──────────────────│
                          │ user_id (PK)     │
                          │ username (UQ)    │
                          │ role             │
                          └──────┬───────────┘
                                 │
              ┌──────────────────┼──────────────────────┐
              │ created_by       │ surveyed_by           │ assigned_to
              │ (FK, nullable)   │ (FK, nullable)        │ (FK, nullable)
              ▼                  ▼                       ▼
┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────────┐
│     villagers        │  │     land_plots       │  │      cases       │
│──────────────────────│  │──────────────────────│  │──────────────────│
│ villager_id (PK)     │  │ plot_id (PK)         │  │ case_id (PK)    │
│ id_card_number (UQ)  │  │ plot_code (UQ)       │  │ case_number(UQ) │
│ prefix               │  │ villager_id (FK) ◄───┤  │ villager_id(FK) │
│ first_name           │  │ parent_plot_id (FK)──┤  │ plot_id (FK)    │
│ last_name            │  │ num_apar             │  │ case_type       │
│ address              │  │ spar_code            │  │ status          │
│ village_no/name      │  │ spar_no (5 หลัก)     │  └────────┬────────┘
│ sub_district         │  │ num_spar (5 หลัก)    │           │
│ district/province    │  │ apar_no              │           │
│ qualification_status │  │ area_rai/ngan/sqwa   │           │
│ verification_status  │  │ lat/lng              │           │
└──────┬───────────────┘  │ status               │           │
       │                  │ remark_risk          │           │
       │                  │ allocation_type      │           │
       │                  └──────────┬───────────┘           │
       │                             │                       │
       │  ┌──────────────────────┐   │                       │
       │  │ household_members    │   │                       │
       │  │──────────────────────│   │                       │
       ├─►│ member_id (PK)      │   │                       │
       │  │ villager_id (FK)    │   │                       │
       │  │ first_name          │   │                       │
       │  │ relationship        │   │                       │
       │  └──────────┬──────────┘   │                       │
       │             │              │                       │
       │             ▼              ▼                       │
       │  ┌────────────────────────────────┐                │
       │  │      plot_allocations          │                │
       │  │────────────────────────────────│                │
       │  │ id (PK)                        │                │
       ├─►│ villager_id (FK)               │                │
       │  │ plot_id (FK)                   │                │
       │  │ member_id (FK, nullable)       │                │
       │  │ allocation_type                │                │
       │  │ allocated_area_rai             │                │
       │  └────────────────────────────────┘                │
       │                                                    │
       │  ┌────────────────────────────────┐                │
       │  │        documents               │                │
       │  │────────────────────────────────│                │
       │  │ doc_id (PK)                    │                │
       │  │ related_type (villager/plot/   ◄────────────────┤
       │  │              case)             │  (polymorphic) │
       │  │ related_id                     │                │
       │  │ doc_category                   │                │
       │  └────────────────────────────────┘                │
       │                                                    │
       │  ┌────────────────────────────────┐                │
       │  │      activity_logs             │                │
       │  │────────────────────────────────│                │
       │  │ log_id (PK)                    │                │
       │  │ user_id (FK → users)           │                │
       │  │ table_name + record_id         │                │
       │  └────────────────────────────────┘                │
       │                                                    │
       │  ┌────────────────────────────────┐                │
       │  │     report_templates           │  (standalone)  │
       │  │────────────────────────────────│                │
       │  │ template_id (PK)              │                │
       │  │ template_code (UQ)            │                │
       │  └────────────────────────────────┘                │
```

### 5.3 Foreign Keys (FK)

| ตาราง | FK Column | → ตารางอ้างอิง | ON DELETE |
|-------|-----------|----------------|-----------|
| `villagers` | `created_by` | → `users.user_id` | SET NULL |
| `land_plots` | `villager_id` | → `villagers.villager_id` | CASCADE |
| `land_plots` | `surveyed_by` | → `users.user_id` | SET NULL |
| `land_plots` | `parent_plot_id` | → `land_plots.plot_id` | (self-ref) |
| `cases` | `villager_id` | → `villagers.villager_id` | CASCADE |
| `cases` | `plot_id` | → `land_plots.plot_id` | SET NULL |
| `cases` | `assigned_to` | → `users.user_id` | SET NULL |
| `cases` | `created_by` | → `users.user_id` | SET NULL |
| `documents` | `uploaded_by` | → `users.user_id` | SET NULL |
| `activity_logs` | `user_id` | → `users.user_id` | CASCADE |
| `household_members` | `villager_id` | → `villagers.villager_id` | CASCADE |
| `plot_allocations` | `plot_id` | → `land_plots.plot_id` | CASCADE |
| `plot_allocations` | `villager_id` | → `villagers.villager_id` | CASCADE |
| `plot_allocations` | `member_id` | → `household_members.member_id` | SET NULL |

### 5.4 ความสัมพันธ์หลัก

- **villagers 1 ──► N land_plots** — ราษฎร 1 คนมีได้หลายแปลง
- **villagers 1 ──► N household_members** — ราษฎร 1 คนมีสมาชิกครัวเรือนหลายคน
- **villagers 1 ──► N cases** — ราษฎร 1 คนมีได้หลายคำร้อง
- **land_plots 1 ──► N plot_allocations** — แปลง 1 แปลงจัดสรรได้หลายรายการ
- **land_plots 1 ──► N land_plots** (self-ref) — แปลงแม่ → แปลงลูก (prefix 3/4)
- **documents** — polymorphic (related_type + related_id → villager/plot/case)

### 5.5 คอลัมน์สำคัญใน `land_plots`
- `num_apar` — **ที่ดินเลขที่** (ขึ้นต้น 1=เดิม, 2=คสช., 3=แบ่งครัวเรือน, 4=ม.19)
- `apar_no` — เขตโครงการ (5 หลัก)
- `spar_no` — เขตสำรวจ (5 หลัก, zero-padded)
- `num_spar` — เลขที่สำรวจ (5 หลัก, zero-padded)
- `parent_plot_id` — แปลงต้นทาง (NULL = แปลงดั้งเดิม, มีค่า = แปลงที่แบ่งมา)
- `allocation_type` — ประเภทการจัดสรร (owner/heir/section19/split)

---

## 6. ระบบหลักที่ต้องรู้

### 6.1 ตรวจสอบสิทธิ์ + จัดสรรที่ดิน (VerificationController)
- ค้นหาราษฎรจากเลขบัตร → ดูเนื้อที่รวม → จัดสรร 3 ขั้นตอน:
  - Step 1: ผู้ครอบครอง ≤20 ไร่
  - Step 2: ทายาท/ครัวเรือน ≤20 ไร่
  - Step 3: ส่วนเกิน 40 ไร่ → ม.19 (อัตโนมัติ)
- **เมื่อกดบันทึก** → สร้าง `land_plots` ใหม่อัตโนมัติ:
  - type `heir` → `num_apar` prefix **3** (30001, 30002, ...)
  - type `section19` → `num_apar` prefix **4** (40001, 40002, ...)
- ลบแปลงเก่า prefix 3/4 ก่อนสร้างใหม่ทุกครั้ง (idempotent)

### 6.2 แบ่งแปลงที่ดิน (SubdivisionController)
- **Dashboard อย่างเดียว** (read-only) — แสดงรายชื่อผู้ครอบครอง >20 ไร่
- ไม่มีฟอร์มสร้าง/ลบแปลง — ทุกอย่างทำผ่านหน้าตรวจสอบสิทธิ์

### 6.3 ฟอร์มราชการ (FormExportController + views/forms/)

#### Design Patterns (ถอดจาก อส.6-1 ที่สมบูรณ์ที่สุด — ใช้เป็นแม่แบบ)
```
┌─ Data Layer ──────────────────────────────────────────────┐
│ FormExportController::getForm61()                         │
│ → SELECT ... FROM land_plots JOIN villagers               │
│ → subquery: owner_total_rai, owner_plot_count             │
│ → filter: par_ban, park_name, apar_no                     │
└───────────────────────────────────────────────────────────┘
         │
         ▼
┌─ Grouping ────────────────────────────────────────────────┐
│ $zoneGroups[apar_no] → แยกตามเขตโครงการ                    │
│ $allPagesFlat[] → array_chunk($zoneRows, 9)               │
│ → ลำดับรีเซ็ตทุกเขต ($zoneRowIdx)                          │
└───────────────────────────────────────────────────────────┘
         │
         ▼
┌─ Pagination ──────────────────────────────────────────────┐
│ foreach ($allPagesFlat as $pageIdx => $pageInfo):          │
│   - Header ซ้ำทุกหน้า (หมู่บ้าน/อุทยาน/เขต/รหัส)          │
│   - <thead> ซ้ำทุกหน้า (คอลัมน์ table-layout: fixed)      │
│   - <tfoot>:                                              │
│       หน้าปกติ → "รวมสะสม X ราย Y แปลง"                    │
│       หน้าสุดท้ายเขต → "รวมทั้งสิ้น" + ล่อแหลม/ไม่ล่อแหลม │
│   - ลายเซ็น 3 ช่อง (เฉพาะหน้าสุดท้ายเขต)                 │
│   - หมายเหตุ (ทุกหน้า)                                     │
└───────────────────────────────────────────────────────────┘
```

**Key conventions:**
- **"ราย"** = unique `villager_id` (1 คนหลายแปลง นับ 1 ราย)
- **"แปลง"** = count rows
- **ผลรวมเนื้อที่** = `getAreaSummary()` ทำ normalize (100 ตร.วา = 1 งาน, 4 งาน = 1 ไร่)
- **spar_no / num_spar** = zero-padded 5 หลักเสมอ
- **Risky count** = `remark_risk IN ('risky','risky_case')`

#### รายละเอียดแต่ละฟอร์ม (เทียบกับแบบมาตรฐานราชการ references/แบบฟอร์ม/)

**`form61.php` — อส.6-1 บัญชีรายชื่อผู้ครอบครอง** ✅ สมบูรณ์
- pagination ✅ | zone grouping ✅ | cumulative totals ✅ | ลายเซ็น 3 ช่อง ✅
- ช่องหมายเหตุ: แสดง "ครอบครอง X แปลง รวม Y ไร่ (เกิน 20/40 ไร่)" เฉพาะกรณีพิเศษ
- คอลัมน์ (ตาม ref): ลำดับ / คำนำหน้า / ชื่อ / นามสกุล / เลขบัตร / ที่ดินเลขที่ / เขตสำรวจ / ที่ดินสำรวจเลขที่ / ไร่-งาน-วา / หมายเหตุ

**`form62.php` — อส.6-2 บัญชีสมาชิกครัวเรือน** ✅ สมบูรณ์
- pagination ✅ | apar_no filter ✅ | cumulative ✅ | ลายเซ็น 2 ช่อง ✅ (ตาม ref)
- คอลัมน์ (ตาม ref): ลำดับ / คำนำหน้า / ชื่อ / นามสกุล / เลขบัตร / ที่ดินเลขที่+ชื่อผู้ครอบครอง / หมายเหตุ
- ลำดับรีเซ็ตทุก group (ตาม ref: นับใหม่ต่อผู้ครอบครอง)

**`form63.php` — อส.6-3 ผู้ไม่ผ่านตรวจสอบ** ✅ สมบูรณ์
- pagination ✅ | apar_no filter ✅ | cumulative ✅ | unique ราย count ✅ | ลายเซ็น 2 ช่อง ✅ (ตาม ref)
- คอลัมน์ (ตาม ref): ลำดับ / คำนำหน้า / ชื่อ / นามสกุล / เลขบัตร / เขตสำรวจ / ที่ดินสำรวจเลขที่ / ไร่-งาน-วา / หมายเหตุ
- หมายเหตุ: แบบราชการ ไม่มีคอลัมน์ "ที่ดินเลขที่" (num_apar)

**`account11.php` — บช.1-1 รายชื่อราษฎร** ✅ เกือบสมบูรณ์
- pagination ✅ | cumulative ✅ | ลายเซ็น 3 ช่อง ✅
- ⚠️ ไม่แยก zone grouping (ถ้าเลือก "ทั้งหมด" ข้อมูลคนละเขตปนกัน)

**`account12.php` — บช.1-2 แปลงที่ดินอื่นๆ** ✅ สมบูรณ์
- pagination ✅ | apar_no filter ✅ | cumulative ✅ | ลายเซ็น 3 ช่อง ✅
- คอลัมน์ (ตาม ref): ลำดับ / ชื่อ / ประเภทหลัก / ประเภทย่อย / ไร่-งาน-วา / หมายเหตุ
- หมายเหตุ: แบบราชการ ไม่มีคอลัมน์เขตสำรวจ
- `classifyPtype()` ใช้ `str_contains` hardcode ตาม ref 5 ประเภท

**`self_cert.php` — หนังสือรับรองตนเอง** ✅ สมบูรณ์
- Portrait A4 | ข้อมูลครบ | checklist 6 ข้อ ดึงจาก qualification ใน DB
- ลายเซ็น 4 ช่อง (ผู้ถือครอง / ผู้ใหญ่บ้าน / พยาน 2)

**`self_cert_heir.php` — หนังสือรับรอง (ทายาท)** ✅ ใช้งานได้
- ⚠️ ยังไม่มีเลขบัตรทายาท / วันเกิด (ปรับภายหลังได้)

### 6.4 แผนที่ (Leaflet.js) — Map Features

#### Shared Functions (`assets/js/map-popup.js`)

ไฟล์นี้ถูก include ในทุกหน้าที่โหลด Leaflet.js (ผ่าน `views/layout/header.php`) มี 2 function:

| Function | หน้าที่ |
|----------|--------|
| `plotPopupHtml(opts)` | สร้าง HTML popup สำหรับแปลงที่ดิน (รหัสแปลง, เจ้าของ, เนื้อที่, สถานะ) |
| `addMapLayers(map, opts)` | เพิ่ม base layer switcher (3 แบบ) + ขอบเขตอุทยานฯ ให้แผนที่ |

#### `addMapLayers(map, opts)` — ใช้ในทุกหน้าที่มีแผนที่

```js
// ตัวอย่างการใช้งาน
const map = L.map('myMap').setView([14.3, 99.0], 12);
addMapLayers(map, { defaultBase: 'satellite' });
// → เพิ่ม 3 base layers (ปกติ/ดาวเทียม/ภูมิประเทศ) + ขอบเขตอุทยานฯ + Layer Control

// หน้าที่มี overlay เพิ่มเติม
addMapLayers(map, {
    defaultBase: 'osm',
    extraOverlays: { 'ขอบเขตแปลง': plotBoundaryLayer }
});
```

**Parameters:**
- `defaultBase` — `'osm'` | `'satellite'` | `'topo'` (default: `'satellite'`)
- `showPark` — แสดงขอบเขตอุทยานฯ เป็นค่าเริ่มต้น (default: `true`)
- `extraOverlays` — overlay เพิ่มเติมเช่น `{ 'ขอบเขตแปลง': someLayerGroup }`

#### หน้าที่มีแผนที่ (5 หน้า) — ทั้งหมดใช้ `addMapLayers()`

| หน้า | Base Map | ขอบเขตอุทยานฯ | Overlay เพิ่ม |
|------|:--------:|:-------------:|:-------------:|
| `views/map/index.php` | OSM (default) | ✅ toggle | + ขอบเขตแปลง |
| `views/plots/detail.php` | ดาวเทียม | ✅ toggle | — |
| `views/villagers/detail.php` | ดาวเทียม | ✅ toggle | — |
| `views/villages/detail.php` | ดาวเทียม | ✅ toggle | — |
| `views/verification/process.php` | OSM | ✅ toggle | — |

#### ขอบเขตอุทยานแห่งชาติเอราวัณ

- **ต้นฉบับ**: `MAP_ERW/NPRK_1012(เอราวัณ)_DNP_WGS1984.shp` (UTM Zone 47N)
- **แปลงด้วย**: `convert_shp.js` (Node.js + shapefile + proj4) — reproject UTM→WGS84
- **ผลลัพธ์**: `data/erawan_boundary.geojson` (54.6 KB, 1 polygon, 2818 จุด)
- **สไตล์**: เส้นประสีฟ้า (`#0ea5e9`), พื้นจางมาก (opacity 5%), มี popup ชื่ออุทยาน
- **วิธีแปลงใหม่** (ถ้าได้ shapefile ใหม่):
```powershell
cmd /c "npm install shapefile proj4"   # ติดตั้งครั้งแรก
cmd /c "node convert_shp.js"           # แปลง SHP → GeoJSON
```

### 6.5 Routing (index.php)
- ใช้ `$_GET['page']` + `$_GET['action']` — ไม่มี framework router
- POST handlers อยู่ก่อน layout render
- Form print pages (`page=forms&action=print&type=form61`) ไม่ใช้ layout (standalone HTML)

---

## 7. หมายเหตุ num_apar (ที่ดินเลขที่)

| Prefix | ความหมาย |
|--------|----------|
| **1xxxx** | แปลงผู้ครอบครองเดิม (มติ ครม. 30 มิ.ย. 2541) |
| **2xxxx** | ไม่ใช่ผู้ครอบครองเดิม (คำสั่ง คสช. 66/2557) |
| **3xxxx** | แบ่งจากแปลง 1/2 ที่เกิน 20 ไร่ → ให้ครัวเรือน/ทายาท |
| **4xxxx** | แบ่งจากแปลง ที่เกิน 40 ไร่ → เข้า ม.19 |

Prefix 3 และ 4 **สร้างอัตโนมัติ** จาก `VerificationController::saveAllocation()` — ไม่ต้องสร้างมือ

---

## 8. Data Import Flow (ขั้นตอนนำเข้าข้อมูล)

ข้อมูลแปลงสำรวจมาจาก 2 แหล่ง:
- **Shapefile (.shp/.dbf)** — ข้อมูลเชิงพื้นที่ + attribute (import ครั้งแรก)
- **Excel (.xlsx)** — ข้อมูลที่เจ้าหน้าที่แก้ไข/ปรับปรุงแล้ว (UPSERT ทับ)

### 8.1 Import ครั้งแรก (จาก Shapefile)
```powershell
& "C:\xampp\php\php.exe" tools/import_shapefile.php
```
- ใช้ `SPAR_CODE` เป็น `plot_code` (UNIQUE key)
- ใช้ `IDCARD` เป็น `id_card_number` ของ villagers (UNIQUE key)
- แปลง UTM (E, N) เป็น lat/lng
- ถ้า SPAR_CODE ซ้ำจะสร้าง `_DUP` suffix

### 8.2 UPSERT จาก Excel (ข้อมูลปรับปรุง)
```powershell
# 1. ตรวจสอบ Excel ก่อน
python tools/inspect_xlsx.py

# 2. แก้ไข artifact ใน Excel (ถ้ามี)
python tools/fix_xlsx.py

# 3. UPSERT เข้า DB
python tools/upsert_xlsx.py

# 4. แก้ไข DUP records
python tools/fix_dup.py

# 5. สร้างรายงานตรวจสอบ
python tools/audit_report.py    # → tools/audit_hardpaper.txt
```

### 8.3 Column Mapping (Excel → DB)

| Excel Column | DB Table | DB Column |
|-------------|----------|----------|
| `IDCARD` | villagers | `id_card_number` |
| `NAME_TITLE` | villagers | `prefix` |
| `NAME` | villagers | `first_name` |
| `SURNAME` | villagers | `last_name` |
| `HOME_NO` + `HOME_MOO` | villagers | `address` |
| `HOME_BAN` | villagers | `village_name` |
| `HOME_MOO` | villagers | `village_no` |
| `HOME_TAM` | villagers | `sub_district` |
| `HOME_AMP` | villagers | `district` |
| `HOME_PROV` | villagers | `province` |
| `SPAR_CODE` | land_plots | `plot_code` (UNIQUE) |
| `NUM_APAR` | land_plots | `num_apar` |
| `APAR_NO` | land_plots | `apar_no` |
| `RAI` / `NGAN` / `WA_SQ` | land_plots | `area_rai` / `area_ngan` / `area_sqwa` |
| `E` / `N` | land_plots | `latitude` / `longitude` (UTM→WGS84) |
| `PTYPE` | land_plots | `ptype` / `land_use_type` |
| `REMARK` | land_plots | `remark_risk` |
| `YEAR` | land_plots | `occupation_since` (พ.ศ.→ค.ศ.) |

### 8.4 สถานะข้อมูลปัจจุบัน (2026-02-13)
- **villagers**: 753 คน
- **land_plots**: 1,103 แปลง
- **DUP records**: 0 (แก้ไขแล้ว — ลบ 76 ซ้ำจริง, เปลี่ยน plot_code 10 แปลง)
- **data_issues**: 1 แปลง (IMP-00450 ไม่มี SPAR_CODE)
- **เลขบัตรต้องตรวจสอบ**: 9 คน (ดู `tools/audit_hardpaper.txt`)

### 8.5 การตรวจสอบคุณภาพข้อมูล Shapefile ก่อน Import (QA Audit)

> **ทำเมื่อไหร่**: ทุกครั้งที่ได้รับ Shapefile ใหม่ หรือเจ้าหน้าที่แก้ไข Shapefile แล้วส่งกลับมา
> ก่อน import/re-import เข้า DB ต้องรัน QA Audit ก่อนเสมอ

#### ขั้นตอน

```
┌─ 1. ได้รับ Shapefile (.shp/.dbf) ──────────────────────────────────┐
│   วางไฟล์ไว้ที่ ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.shp             │
└────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─ 2. รัน QA Audit Scripts ──────────────────────────────────────────┐
│   # ตรวจสอบ Attribute ทั้งหมด                                      │
│   & "C:\xampp\php\php.exe" tools/shp_audit.php                     │
│                                                                    │
│   # เปรียบเทียบเนื้อที่ SHP vs DB                                   │
│   & "C:\xampp\php\php.exe" tools/compare_area_shp_db.php           │
│                                                                    │
│   # สร้างรายงาน QA Checklist (.txt) สำหรับส่งเจ้าหน้าที่           │
│   & "C:\xampp\php\php.exe" tools/generate_qa_checklist.php         │
│   → Output: tools/SHP_QA_Checklist_YYYYMMDD.txt                   │
└────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─ 3. ส่งรายงานให้เจ้าหน้าที่ ──────────────────────────────────────┐
│   ส่งไฟล์ SHP_QA_Checklist_YYYYMMDD.txt ให้เจ้าหน้าที่ภาคสนาม     │
│   เจ้าหน้าที่เปิด Shapefile ใน ArcGIS/QGIS แก้ไข Attribute        │
│   ตามรายการในรายงาน แล้วส่งไฟล์ .shp ที่แก้แล้วกลับมา             │
└────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─ 4. รัน QA Audit อีกครั้ง (ยืนยันว่าแก้ครบ) ─────────────────────┐
│   รัน generate_qa_checklist.php อีกรอบ                             │
│   ถ้าผ่านทุกส่วน → import/re-import เข้า DB ได้                    │
└────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─ 5. Import เข้า DB ───────────────────────────────────────────────┐
│   & "C:\xampp\php\php.exe" tools/import_shapefile.php              │
└────────────────────────────────────────────────────────────────────┘
```

#### รายการตรวจสอบ (6 ส่วน)

| ส่วน | ตรวจอะไร | วิธีแก้ |
|------|----------|---------|
| **1. SPAR_CODE ซ้ำ** | 1 SPAR_CODE มีหลาย records ใน .dbf | เจ้าหน้าที่เลือกเก็บ record ที่ถูกต้อง ลบ record ซ้ำออก |
| **2. IDCARD ผิดรูปแบบ** | เลขบัตรไม่ใช่ 13 หลัก / มีช่องว่าง / มีอักขระพิเศษ | แก้ไขใน Attribute Table ให้เป็น 13 หลักตัวเลข |
| **3. SPAR_CODE ว่าง** | record ไม่มี SPAR_CODE (ไม่สามารถ import ได้) | เจ้าหน้าที่กำหนด SPAR_CODE ให้ |
| **4. REMARK ไม่มาตรฐาน** | ค่า REMARK ควรเป็น "ล่อแหลม" หรือ "ไม่ล่อแหลม" เท่านั้น | ข้อมูลเพิ่มเติม (มอบอำนาจ/เสียชีวิต ฯลฯ) ควรแยกไปช่องอื่น |
| **5. เนื้อที่ SHP vs DB** | เปรียบเทียบ AREA_RAI + NGAN + WA_SQ กับ DB | ปกติตรง 100% หลังลบ records ซ้ำ ถ้าไม่ตรงต้องตรวจเนื้อที่ |
| **6. SPAR_CODE prefix** | SPAR_CODE ควรขึ้นต้นด้วยตัวอักษร 3 ตัว (BKR, PDS, TSL) | ถ้าขึ้นต้นด้วยตัวเลข (101...) ต้องยืนยันว่าถูกต้อง |

#### Scripts ที่เกี่ยวข้อง

| ไฟล์ | หน้าที่ |
|------|---------|
| `tools/shp_audit.php` | ตรวจสอบ Attribute ทั้งหมด แสดงสรุปปัญหา |
| `tools/compare_area_shp_db.php` | เปรียบเทียบเนื้อที่ SHP vs DB รายแปลง |
| `tools/area_summary.php` | สรุปจำนวนแปลง + เนื้อที่รวม ทั้ง SHP และ DB |
| `tools/generate_qa_checklist.php` | สร้างรายงาน .txt สำหรับส่งเจ้าหน้าที่ (UTF-8 BOM) |

#### หมายเหตุสำคัญ
- **SHP เป็นข้อมูลอ้างอิง** — เนื้อที่ใน SHP มาจากการรังวัด ถือเป็นค่าที่ถูกต้อง
- **ปัญหาหลัก = SPAR_CODE ซ้ำ** — ทำให้เนื้อที่รวมใน SHP มากกว่า DB (เพราะนับซ้ำ)
- **รายงาน .txt เข้ารหัส UTF-8 BOM** — เปิดด้วย Notepad/Excel ภาษาไทยได้เลย
- **ต้องรัน QA ทุกครั้ง** ก่อน import — แม้เจ้าหน้าที่บอกว่าแก้แล้ว ก็ต้องตรวจซ้ำ

---

## 9. Deploy บน Railway (Production)

> **อัพเดทล่าสุด**: 2026-02-14 — deploy สำเร็จ + แก้ปัญหา encoding ภาษาไทย

### 9.1 สถาปัตยกรรม

```
GitHub (main branch)
    │
    ▼  auto-deploy on push
┌─────────────────────────────────────────────┐
│  Railway Project: upbeat-fascination        │
│                                             │
│  ┌─────────────────┐  ┌──────────────────┐  │
│  │ Land-management │  │     MySQL        │  │
│  │ -ERW (PHP app)  │──│  (DB: railway)   │  │
│  │ Dockerfile      │  │  mysql.railway   │  │
│  │ php:8.2-cli     │  │  .internal:3306  │  │
│  └─────────────────┘  └──────────────────┘  │
│                                             │
│  Environment Variables:                     │
│  - MYSQL_URL (auto-injected)                │
│  - PORT (auto-injected)                     │
└─────────────────────────────────────────────┘
```

### 9.2 ไฟล์ที่เกี่ยวข้อง

| ไฟล์ | หน้าที่ |
|------|---------|
| `Dockerfile` | Build image: `php:8.2-cli` + `pdo_mysql` + built-in server บน `$PORT` |
| `config/database.php` | Parse `MYSQL_URL` อัตโนมัติ (Railway inject ให้) |
| `setup.php` | Web-based DB import — เปิดครั้งเดียวหลัง deploy |
| `sql/full_backup_railway.sql` | Backup ข้อมูลจริงทั้งหมด (export จาก local) |
| `.dockerignore` | ไม่ copy `.env`, `.git`, `tools/`, `sql/schema.sql` เข้า container |
| `.gitignore` | ไม่ push `.env`, uploads, shapefile data |

### 9.3 ขั้นตอน Deploy (ครั้งแรก)

```
1. สร้าง Railway Project → New Project
2. เพิ่ม MySQL service → New Service → MySQL
3. เพิ่ม PHP app → Deploy from GitHub repo (vwin2537-arch/Land-management-ERW)
4. Railway จะ inject MYSQL_URL ให้ app อัตโนมัติ (ถ้าอยู่ project เดียวกัน)
5. รอ build จาก Dockerfile (~1-2 นาที)
6. เปิด https://your-app.railway.app/setup.php → import DB
7. Login: admin / admin123
```

### 9.4 ขั้นตอน Re-deploy (อัพเดทข้อมูล)

```powershell
# 1. Export DB backup ใหม่จาก local (ใช้ --result-file เท่านั้น!)
C:\xampp\mysql\bin\mysqldump.exe -u root --default-character-set=utf8mb4 --routines --triggers --result-file=sql\full_backup_railway.sql land_management

# 2. Commit + Push
git add -A
git commit -m "update backup"
git push origin main

# 3. รอ Railway auto-deploy (~1-2 นาที)

# 4. เปิด setup.php?reset=1 เพื่อ drop + re-import
# https://your-app.railway.app/setup.php?reset=1
```

### 9.5 ปัญหาที่เจอ + วิธีแก้ (Troubleshooting)

#### ❌ ปัญหา 1: `0 statements executed` — SQL split ไม่ทำงาน

**อาการ**: setup.php แสดง "OK: 0 statements executed" + "Syntax error near ''"
**สาเหตุ**: Backup file มี CRLF line ending (`\r\n`) แต่ PHP `explode(";\n")` ตัดไม่ถูก
**สาเหตุรอง**: UTF-8 BOM (3 bytes `EF BB BF`) ติดหน้า SQL ทำให้ statement แรกพัง

**วิธีแก้ (ใน setup.php)**:
```php
// Strip UTF-8 BOM
if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
    $sql = substr($sql, 3);
}
// Normalize CRLF → LF
$sql = str_replace("\r\n", "\n", $sql);
$sql = str_replace("\r", "\n", $sql);
// ใช้ preg_split แทน explode
$statements = preg_split('/;\s*\n/', $sql);
```

**วิธีป้องกัน**: Export ด้วย `--result-file` (binary write) ไม่ใช่ pipe ผ่าน PowerShell
```powershell
# ✅ ถูก — ใช้ --result-file (ไม่ผ่าน pipe)
mysqldump --result-file=sql\full_backup_railway.sql land_management

# ❌ ผิด — pipe ผ่าน PowerShell จะเพิ่ม BOM + เปลี่ยน encoding
mysqldump land_management | Out-File sql\full_backup_railway.sql

# ❌ ผิด — redirect > ใน PowerShell ก็เปลี่ยน encoding
mysqldump land_management > sql\full_backup_railway.sql
```

#### ❌ ปัญหา 2: ภาษาไทยเพี้ยน (mojibake) — `เปิเรดนเปิเรชเรดเปิ`

**อาการ**: ข้อมูลภาษาไทยแสดงเป็นอักขระเพี้ยน ทั้ง dashboard และทะเบียนราษฎร
**สาเหตุ**: Backup file ถูก export ผ่าน PowerShell pipe ซึ่งเปลี่ยน encoding จาก UTF-8 เป็น UTF-16 หรือ Windows-1252

**วิธีแก้**:
1. Re-export ด้วย `--result-file` + `--default-character-set=utf8mb4`:
```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root --default-character-set=utf8mb4 --routines --triggers --result-file=sql\full_backup_railway.sql land_management
```
2. เพิ่ม `SET NAMES utf8mb4` ใน setup.php ก่อน import:
```php
$db->exec("SET NAMES utf8mb4");
$db->exec("SET CHARACTER SET utf8mb4");
```
3. Reset DB บน Railway: เปิด `setup.php?reset=1`

#### ❌ ปัญหา 3: `Table 'railway.users' doesn't exist`

**อาการ**: เปิดหน้า app แล้ว error ว่าไม่มี table
**สาเหตุ**: Railway MySQL ใช้ DB ชื่อ `railway` (ไม่ใช่ `land_management`) — ต้องรัน setup.php ก่อน
**วิธีแก้**: เปิด `/setup.php` เพื่อ import tables เข้า DB `railway`

#### ❌ ปัญหา 4: MariaDB → MySQL 9.x compatibility

**อาการ**: SQL error เรื่อง collation หรือ CHECK constraint
**สาเหตุ**: XAMPP ใช้ MariaDB แต่ Railway ใช้ MySQL 9.x ซึ่งไม่รองรับบาง syntax

**วิธีแก้ (setup.php ทำอัตโนมัติ)**:
```php
// แก้ collation ที่ MySQL 9.x ไม่รู้จัก
$sql = str_replace('utf8mb4_thai_520_w2', 'utf8mb4_unicode_ci', $sql);
// ลบ CHECK constraint ที่ MariaDB สร้าง
$sql = preg_replace('/\s+CHECK\s*\(json_valid\(`[^`]+`\)\)/', '', $sql);
// ลบ USE database (Railway ใช้ชื่อ DB ต่างกัน)
$sql = preg_replace('/^USE\s+`?land_management`?\s*;?\s*$/m', '', $sql);
```

### 9.6 ข้อจำกัดของ Railway

| ข้อจำกัด | รายละเอียด |
|----------|-----------|
| **Ephemeral storage** | ไฟล์ที่ upload (photos, documents) จะหายเมื่อ redeploy — ต้องใช้ S3/R2 ถ้าต้องการเก็บถาวร |
| **DB ชื่อ `railway`** | Railway MySQL ใช้ DB ชื่อ `railway` ไม่ใช่ `land_management` — `database.php` parse จาก `MYSQL_URL` อัตโนมัติ |
| **Credit limit** | Free tier มี $5/เดือน — ดู usage ที่ Railway dashboard |
| **Build time** | Auto-deploy ทุกครั้งที่ push — build ใช้เวลา ~1-2 นาที |
| **setup.php ต้อง protect** | หลัง import เสร็จ ควรลบ setup.php หรือเพิ่ม auth check ป้องกันคนอื่นเข้าถึง |

### 9.7 Checklist ก่อน Push ขึ้น Production

- [ ] Export backup ใหม่ด้วย `--result-file` (ไม่ใช่ pipe)
- [ ] ตรวจว่า `sql/full_backup_railway.sql` ไม่ถูก gitignore
- [ ] ตรวจว่า `.env` ถูก gitignore (ห้าม push credentials)
- [ ] ตรวจว่า `uploads/` ถูก gitignore (ไม่ push ไฟล์ upload)
- [ ] Commit + Push + รอ build เสร็จ
- [ ] เปิด `/setup.php` (ครั้งแรก) หรือ `/setup.php?reset=1` (re-import)
- [ ] ทดสอบ login + ตรวจภาษาไทย

---

## 10. คำสั่งที่ใช้บ่อย

```powershell
# รัน migration
& "C:\xampp\php\php.exe" run_migration.php sql/<filename>.sql

# เช็คข้อมูลผ่าน PHP
& "C:\xampp\php\php.exe" <script>.php

# Export DB backup
C:\xampp\mysql\bin\mysqldump -u root land_management > sql/backup.sql

# Import DB backup
C:\xampp\mysql\bin\mysql -u root land_management < sql/backup.sql

# Python tools (ต้องติดตั้ง dependencies ก่อน)
pip install openpyxl pymysql pyshp pyproj
python tools/upsert_xlsx.py       # UPSERT Excel → DB
python tools/audit_report.py      # สร้างรายงานตรวจสอบ
```

---

## 11. ข้อควรระวัง

- **PHP ไม่อยู่ใน PATH** — ต้องใช้ `C:\xampp\php\php.exe` (full path) ใน PowerShell
- **PowerShell escaping** — ใช้ `& "path"` สำหรับ path ที่มี space / ใช้ไฟล์ .php แทน inline `-r`
- **Collation** — ใช้ `utf8mb4_thai_520_w2` สำหรับ MariaDB (XAMPP), Railway MySQL ใช้ `utf8mb4_unicode_ci`
- **CSRF** — ทุก POST form ต้องมี `<input name="_csrf_token">` (ใช้ `csrf_token()` หรือ `csrf_field()`)
- **Migration idempotent** — ทุก migration เช็ค IF NOT EXISTS / IF column exists แล้ว รันซ้ำได้
- **OneDrive sync** — โปรเจคอยู่ใน OneDrive ใช้ symbolic link เข้า htdocs
- **Excel import** — ตรวจสอบ `_x000D_` artifact + HOME_NO date format ก่อน UPSERT เสมอ (ใช้ `inspect_xlsx.py`)
- **SPAR_CODE ซ้ำ** — 1 SPAR_CODE อาจมีหลายแปลง (NUM_APAR ต่างกัน หรือ polygon ต่างกัน) ห้ามลบโดยไม่เช็ค .shp
- **Python tools** — ต้อง `pip install openpyxl pymysql pyshp pyproj` ก่อนใช้งาน
