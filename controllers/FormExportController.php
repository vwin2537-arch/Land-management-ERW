<?php
/**
 * FormExportController — Export แบบฟอร์มราชการ (อส.6-1, 6-2, 6-3, บัญชี 1-1, 1-2, หนังสือรับรองตนเอง)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class FormExportController
{

    /**
     * อส.6-1: บัญชีรายชื่อผู้ครอบครองที่ดิน (ประเภทผู้ครอบครอง)
     */
    public static function getForm61(array $filters = []): array
    {
        $db = getDB();
        $where = "1=1";
        $params = [];

        if (!empty($filters['par_ban'])) {
            $where .= " AND lp.par_ban = :pb";
            $params['pb'] = $filters['par_ban'];
        }
        if (!empty($filters['park_name'])) {
            $where .= " AND lp.park_name = :pn";
            $params['pn'] = $filters['park_name'];
        }
        if (!empty($filters['apar_no'])) {
            $where .= " AND lp.apar_no = :an";
            $params['an'] = $filters['apar_no'];
        }

        $stmt = $db->prepare("SELECT 
            v.prefix, v.first_name, v.last_name, v.id_card_number,
            lp.num_apar, lp.spar_no, lp.num_spar,
            lp.area_rai, lp.area_ngan, lp.area_sqwa,
            lp.remark_risk, lp.watershed_class, lp.data_issues,
            lp.par_ban, lp.par_moo, lp.par_tam, lp.par_amp, lp.par_prov,
            lp.park_name, lp.code_dnp, lp.apar_no, lp.ban_e,
            v.qualification_status, v.villager_id, lp.plot_id,
            (SELECT SUM(lp2.area_rai + lp2.area_ngan/4 + lp2.area_sqwa/400) FROM land_plots lp2 WHERE lp2.villager_id = v.villager_id) as owner_total_rai,
            (SELECT COUNT(*) FROM land_plots lp3 WHERE lp3.villager_id = v.villager_id) as owner_plot_count
            FROM land_plots lp
            JOIN villagers v ON lp.villager_id = v.villager_id
            WHERE $where
            ORDER BY lp.apar_no ASC, lp.num_apar ASC, lp.plot_id ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * อส.6-2: บัญชีรายชื่อสมาชิกครอบครัว/ครัวเรือน
     */
    public static function getForm62(array $filters = []): array
    {
        $db = getDB();
        $where = "1=1";
        $params = [];

        if (!empty($filters['par_ban'])) {
            $where .= " AND lp.par_ban = :pb";
            $params['pb'] = $filters['par_ban'];
        }
        if (!empty($filters['park_name'])) {
            $where .= " AND lp.park_name = :pn";
            $params['pn'] = $filters['park_name'];
        }
        if (!empty($filters['apar_no'])) {
            $where .= " AND lp.apar_no = :an";
            $params['an'] = $filters['apar_no'];
        }

        $stmt = $db->prepare("SELECT 
            v.villager_id, v.prefix as owner_prefix, v.first_name as owner_first, 
            v.last_name as owner_last, v.id_card_number as owner_idcard,
            lp.num_apar,
            hm.prefix as member_prefix, hm.first_name as member_first,
            hm.last_name as member_last, hm.id_card_number as member_idcard,
            hm.relationship,
            lp.par_ban, lp.par_moo, lp.par_tam, lp.par_amp, lp.par_prov,
            lp.park_name, lp.code_dnp, lp.apar_no
            FROM villagers v
            JOIN land_plots lp ON lp.villager_id = v.villager_id
            LEFT JOIN household_members hm ON hm.villager_id = v.villager_id
            WHERE $where
            ORDER BY lp.apar_no, v.villager_id, lp.num_apar, hm.member_id");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * อส.6-3: บัญชีรายชื่อผู้ไม่ผ่านการตรวจสอบคุณสมบัติ
     */
    public static function getForm63(array $filters = []): array
    {
        $db = getDB();
        $where = "v.qualification_status = 'failed'";
        $params = [];

        if (!empty($filters['par_ban'])) {
            $where .= " AND lp.par_ban = :pb";
            $params['pb'] = $filters['par_ban'];
        }
        if (!empty($filters['park_name'])) {
            $where .= " AND lp.park_name = :pn";
            $params['pn'] = $filters['park_name'];
        }
        if (!empty($filters['apar_no'])) {
            $where .= " AND lp.apar_no = :an";
            $params['an'] = $filters['apar_no'];
        }

        $stmt = $db->prepare("SELECT 
            v.prefix, v.first_name, v.last_name, v.id_card_number,
            v.qualification_notes,
            lp.spar_no, lp.num_spar,
            lp.area_rai, lp.area_ngan, lp.area_sqwa,
            lp.remark_risk, lp.data_issues,
            lp.par_ban, lp.par_moo, lp.par_tam, lp.par_amp, lp.par_prov,
            lp.park_name, lp.code_dnp, lp.apar_no
            FROM land_plots lp
            JOIN villagers v ON lp.villager_id = v.villager_id
            WHERE $where
            ORDER BY lp.apar_no ASC, lp.plot_id ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * บัญชี 1-1: รายชื่อราษฎรพร้อมจำนวนที่ดิน (รายหมู่บ้าน)
     */
    public static function getAccount11(array $filters = []): array
    {
        $db = getDB();
        $where = "1=1";
        $params = [];

        if (!empty($filters['par_ban'])) {
            $where .= " AND lp.par_ban = :pb";
            $params['pb'] = $filters['par_ban'];
        }
        if (!empty($filters['park_name'])) {
            $where .= " AND lp.park_name = :pn";
            $params['pn'] = $filters['park_name'];
        }
        if (!empty($filters['apar_no'])) {
            $where .= " AND lp.apar_no = :an";
            $params['an'] = $filters['apar_no'];
        }

        $stmt = $db->prepare("SELECT 
            v.prefix, v.first_name, v.last_name, v.id_card_number,
            lp.num_apar, lp.spar_no, lp.num_spar,
            lp.area_rai, lp.area_ngan, lp.area_sqwa,
            lp.remark_risk, lp.watershed_class,
            lp.par_ban, lp.par_moo, lp.par_tam, lp.par_amp, lp.par_prov,
            lp.park_name, lp.code_dnp, lp.apar_no, lp.ban_e,
            (SELECT SUM(lp2.area_rai + lp2.area_ngan/4 + lp2.area_sqwa/400) FROM land_plots lp2 WHERE lp2.villager_id = v.villager_id) as owner_total_rai
            FROM land_plots lp
            JOIN villagers v ON lp.villager_id = v.villager_id
            WHERE $where
            ORDER BY lp.num_apar ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * บัญชี 1-2: แปลงที่ดินประเภทอื่นๆ (วัด โรงเรียน ที่ราชพัสดุ ฯลฯ)
     * ใช้แปลงที่มี ptype เป็นค่าพิเศษ
     */
    public static function getAccount12(array $filters = []): array
    {
        $db = getDB();
        $where = "lp.ptype NOT IN ('ที่อยู่อาศัย','ที่ทำกิน','ที่อยู่อาศัยและที่ทำกิน') OR lp.ptype IS NULL";
        $params = [];

        if (!empty($filters['park_name'])) {
            $where = "($where) AND lp.park_name = :pn";
            $params['pn'] = $filters['park_name'];
        }
        if (!empty($filters['apar_no'])) {
            $where = "($where) AND lp.apar_no = :an";
            $params['an'] = $filters['apar_no'];
        }

        $stmt = $db->prepare("SELECT 
            lp.plot_code, lp.ptype, lp.notes,
            lp.area_rai, lp.area_ngan, lp.area_sqwa,
            lp.remark_risk,
            lp.par_ban, lp.par_moo, lp.par_tam, lp.par_amp, lp.par_prov,
            lp.park_name, lp.code_dnp, lp.apar_no,
            v.prefix, v.first_name, v.last_name
            FROM land_plots lp
            LEFT JOIN villagers v ON lp.villager_id = v.villager_id
            WHERE $where
            ORDER BY lp.apar_no ASC, lp.plot_id ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * หนังสือรับรองตนเอง — ดึงข้อมูลราษฎรรายคน
     */
    public static function getSelfCert(int $villagerId): ?array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT v.*, 
            (SELECT GROUP_CONCAT(lp.park_name SEPARATOR ', ') FROM land_plots lp WHERE lp.villager_id = v.villager_id LIMIT 1) as park_name,
            (SELECT lp.par_ban FROM land_plots lp WHERE lp.villager_id = v.villager_id LIMIT 1) as par_ban,
            (SELECT lp.par_moo FROM land_plots lp WHERE lp.villager_id = v.villager_id LIMIT 1) as par_moo,
            (SELECT lp.par_tam FROM land_plots lp WHERE lp.villager_id = v.villager_id LIMIT 1) as par_tam,
            (SELECT lp.par_amp FROM land_plots lp WHERE lp.villager_id = v.villager_id LIMIT 1) as par_amp,
            (SELECT lp.par_prov FROM land_plots lp WHERE lp.villager_id = v.villager_id LIMIT 1) as par_prov
            FROM villagers v WHERE v.villager_id = :id");
        $stmt->execute(['id' => $villagerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get filter options (villages, parks, apar_no list)
     */
    public static function getFilterOptions(): array
    {
        $db = getDB();

        // Village list with full details for display
        $villages = $db->query("SELECT DISTINCT par_ban, ban_e, par_moo, par_tam, par_amp, par_prov 
            FROM land_plots 
            WHERE par_ban IS NOT NULL AND par_ban != '' 
            ORDER BY par_ban")->fetchAll(PDO::FETCH_ASSOC);

        // Village → apar_no mapping for dynamic dropdown filtering
        $villageApars = $db->query("SELECT par_ban, GROUP_CONCAT(DISTINCT apar_no ORDER BY apar_no) as apar_list 
            FROM land_plots 
            WHERE par_ban IS NOT NULL AND par_ban != '' AND apar_no IS NOT NULL 
            GROUP BY par_ban 
            ORDER BY par_ban")->fetchAll(PDO::FETCH_ASSOC);

        $villageAparMap = [];
        foreach ($villageApars as $va) {
            $villageAparMap[$va['par_ban']] = array_filter(explode(',', $va['apar_list']), fn($v) => $v !== '' && $v !== '-');
        }

        // All apar_no (flat list for initial load)
        $aparNos = $db->query("SELECT DISTINCT apar_no FROM land_plots WHERE apar_no IS NOT NULL AND apar_no != '' AND apar_no != '-' ORDER BY apar_no")->fetchAll(PDO::FETCH_COLUMN);

        $villagerList = $db->query("SELECT villager_id, prefix, first_name, last_name, id_card_number FROM villagers ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

        return compact('villages', 'aparNos', 'villageAparMap', 'villagerList');
    }

    /**
     * Get area summary for the form footer
     */
    public static function getAreaSummary(array $rows): array
    {
        $totalRai = 0;
        $totalNgan = 0;
        $totalSqwa = 0;
        $riskyCount = 0;
        $notRiskyCount = 0;

        foreach ($rows as $row) {
            $totalRai += (int)($row['area_rai'] ?? 0);
            $totalNgan += (int)($row['area_ngan'] ?? 0);
            $totalSqwa += (int)($row['area_sqwa'] ?? 0);

            $risk = $row['remark_risk'] ?? 'not_risky';
            if (in_array($risk, ['risky', 'risky_case'])) {
                $riskyCount++;
            } else {
                $notRiskyCount++;
            }
        }

        // Normalize: 100 sqwa = 1 ngan, 4 ngan = 1 rai
        $totalNgan += intdiv($totalSqwa, 100);
        $totalSqwa = $totalSqwa % 100;
        $totalRai += intdiv($totalNgan, 4);
        $totalNgan = $totalNgan % 4;

        return [
            'rai' => $totalRai,
            'ngan' => $totalNgan,
            'sqwa' => $totalSqwa,
            'total_plots' => count($rows),
            'risky_count' => $riskyCount,
            'not_risky_count' => $notRiskyCount,
        ];
    }

    /**
     * REMARK label
     */
    public static function remarkLabel(?string $risk): string
    {
        return match ($risk) {
            'risky' => 'เป็นพื้นที่ล่อแหลมฯ',
            'risky_case' => 'ล่อแหลม/แปลงคดี',
            'not_risky_case' => 'ไม่ล่อแหลม/แปลงคดี',
            default => '',
        };
    }
}
