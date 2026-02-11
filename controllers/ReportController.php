<?php
/**
 * ReportController — รายงาน + Preview + Export Excel
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class ReportController
{

    /**
     * Get data for a specific report template
     */
    public static function getData(string $code, array $filters = []): array
    {
        $db = getDB();

        switch ($code) {
            case 'RPT_VILLAGER_LIST':
                return self::getVillagerList($db, $filters);
            case 'RPT_PLOT_REGISTRY':
                return self::getPlotRegistry($db, $filters);
            case 'RPT_PLOT_SURVEY':
                return self::getPlotSurvey($db, $filters);
            case 'RPT_ZONE_SUMMARY':
                return self::getZoneSummary($db, $filters);
            case 'RPT_LANDUSE_SUMMARY':
                return self::getLanduseSummary($db, $filters);
            case 'RPT_CASE_STATUS':
                return self::getCaseStatus($db, $filters);
            case 'RPT_CASE_DETAIL':
                return self::getCaseDetail($db, $filters);
            case 'RPT_EXECUTIVE':
                return self::getExecutiveSummary($db, $filters);
            case 'RPT_DOCUMENT_LIST':
                return self::getDocumentList($db, $filters);
            case 'RPT_ACTIVITY_LOG':
                return self::getActivityLog($db, $filters);
            default:
                return [];
        }
    }

    /**
     * Export data as Excel (TSV with .xls extension)
     */
    public static function exportExcel(string $code, array $filters = []): void
    {
        $data = self::getData($code, $filters);
        $db = getDB();
        $tpl = $db->prepare("SELECT template_name FROM report_templates WHERE template_code = :code");
        $tpl->execute(['code' => $code]);
        $tplName = $tpl->fetchColumn() ?: 'Report';

        $filename = $tplName . '_' . date('Y-m-d') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Thai characters

        if (empty($data)) {
            echo "ไม่พบข้อมูล";
            exit;
        }

        // Headers
        echo implode("\t", array_keys($data[0])) . "\n";

        // Data rows
        foreach ($data as $row) {
            $values = array_map(function ($v) {
                return str_replace(["\t", "\n", "\r"], ' ', $v ?? '');
            }, array_values($row));
            echo implode("\t", $values) . "\n";
        }

        exit;
    }

    // ====== Report Data Queries ======

    private static function getVillagerList(PDO $db, array $f): array
    {
        $where = "1=1";
        $params = [];
        if (!empty($f['search'])) {
            $where .= " AND (v.first_name LIKE :s OR v.last_name LIKE :s OR v.id_card_number LIKE :s OR v.village_name LIKE :s)";
            $params['s'] = '%' . $f['search'] . '%';
        }
        if (!empty($f['province'])) {
            $where .= " AND v.province = :prov";
            $params['prov'] = $f['province'];
        }

        $stmt = $db->prepare("SELECT ROW_NUMBER() OVER (ORDER BY v.villager_id) as 'ลำดับ',
            v.id_card_number as 'เลขบัตรประชาชน',
            CONCAT(IFNULL(v.prefix,''), v.first_name, ' ', v.last_name) as 'ชื่อ-นามสกุล',
            v.phone as 'โทรศัพท์',
            CONCAT(IFNULL(v.address,''), ' หมู่ ', IFNULL(v.village_no,''), ' ', IFNULL(v.village_name,'')) as 'ที่อยู่',
            v.sub_district as 'ตำบล', v.district as 'อำเภอ', v.province as 'จังหวัด',
            (SELECT COUNT(*) FROM land_plots lp WHERE lp.villager_id = v.villager_id) as 'จำนวนแปลง'
            FROM villagers v WHERE $where ORDER BY v.villager_id");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getPlotRegistry(PDO $db, array $f): array
    {
        $where = "1=1";
        $params = [];
        if (!empty($f['status'])) {
            $where .= " AND lp.status = :st";
            $params['st'] = $f['status'];
        }
        if (!empty($f['land_use_type'])) {
            $where .= " AND lp.land_use_type = :lut";
            $params['lut'] = $f['land_use_type'];
        }
        if (!empty($f['zone'])) {
            $where .= " AND lp.zone = :zone";
            $params['zone'] = $f['zone'];
        }

        $stmt = $db->prepare("SELECT ROW_NUMBER() OVER (ORDER BY lp.plot_id) as 'ลำดับ',
            lp.plot_code as 'รหัสแปลง',
            CONCAT(IFNULL(v.prefix,''), v.first_name, ' ', v.last_name) as 'เจ้าของ',
            v.id_card_number as 'เลขบัตร',
            lp.area_rai as 'ไร่', lp.area_ngan as 'งาน', lp.area_sqwa as 'ตร.วา',
            CASE lp.land_use_type WHEN 'agriculture' THEN 'เกษตรกรรม' WHEN 'residential' THEN 'ที่อยู่อาศัย' WHEN 'garden' THEN 'ทำสวน' WHEN 'livestock' THEN 'เลี้ยงสัตว์' WHEN 'mixed' THEN 'ผสม' ELSE 'อื่นๆ' END as 'ประเภทการใช้',
            lp.crop_type as 'พืช/รายละเอียด',
            CASE lp.status WHEN 'surveyed' THEN 'สำรวจแล้ว' WHEN 'pending_review' THEN 'รอตรวจสอบ' WHEN 'temporary_permit' THEN 'อนุญาตชั่วคราว' WHEN 'must_relocate' THEN 'ต้องอพยพ' WHEN 'disputed' THEN 'มีข้อพิพาท' END as 'สถานะ',
            IFNULL(lp.zone,'-') as 'โซน'
            FROM land_plots lp JOIN villagers v ON lp.villager_id = v.villager_id
            WHERE $where ORDER BY lp.plot_code");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getPlotSurvey(PDO $db, array $f): array
    {
        $where = "1=1";
        $params = [];
        if (!empty($f['plot_id'])) {
            $where .= " AND lp.plot_id = :pid";
            $params['pid'] = $f['plot_id'];
        }
        if (!empty($f['status'])) {
            $where .= " AND lp.status = :st";
            $params['st'] = $f['status'];
        }

        $stmt = $db->prepare("SELECT lp.plot_id, lp.plot_code, lp.park_name, lp.zone,
            lp.area_rai, lp.area_ngan, lp.area_sqwa, lp.land_use_type, lp.crop_type,
            lp.latitude, lp.longitude, lp.occupation_since, lp.has_document, lp.document_type,
            lp.status, lp.survey_date, lp.plot_image_path, lp.notes,
            v.prefix, v.first_name, v.last_name, v.id_card_number, v.phone, v.address, v.village_name,
            u.full_name as surveyor_name
            FROM land_plots lp
            JOIN villagers v ON lp.villager_id = v.villager_id
            LEFT JOIN users u ON lp.surveyed_by = u.user_id
            WHERE $where ORDER BY lp.plot_code");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getZoneSummary(PDO $db, array $f): array
    {
        $stmt = $db->query("SELECT 
            IFNULL(lp.zone, 'ไม่ระบุ') as 'โซน',
            COUNT(*) as 'จำนวนแปลง',
            COUNT(DISTINCT lp.villager_id) as 'จำนวนราษฎร',
            SUM(lp.area_rai) as 'พื้นที่รวม (ไร่)',
            SUM(CASE WHEN lp.status = 'surveyed' THEN 1 ELSE 0 END) as 'สำรวจแล้ว',
            SUM(CASE WHEN lp.status = 'pending_review' THEN 1 ELSE 0 END) as 'รอตรวจสอบ',
            SUM(CASE WHEN lp.status = 'temporary_permit' THEN 1 ELSE 0 END) as 'อนุญาตชั่วคราว',
            SUM(CASE WHEN lp.status = 'must_relocate' THEN 1 ELSE 0 END) as 'ต้องอพยพ',
            SUM(CASE WHEN lp.status = 'disputed' THEN 1 ELSE 0 END) as 'มีข้อพิพาท'
            FROM land_plots lp GROUP BY lp.zone ORDER BY lp.zone");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getLanduseSummary(PDO $db, array $f): array
    {
        $stmt = $db->query("SELECT 
            CASE lp.land_use_type WHEN 'agriculture' THEN 'เกษตรกรรม' WHEN 'residential' THEN 'ที่อยู่อาศัย' WHEN 'garden' THEN 'ทำสวน' WHEN 'livestock' THEN 'เลี้ยงสัตว์' WHEN 'mixed' THEN 'ผสม' ELSE 'อื่นๆ' END as 'ประเภทการใช้ที่ดิน',
            COUNT(*) as 'จำนวนแปลง',
            COUNT(DISTINCT lp.villager_id) as 'จำนวนราษฎร',
            SUM(lp.area_rai) as 'พื้นที่ (ไร่)',
            SUM(lp.area_ngan) as 'พื้นที่ (งาน)',
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM land_plots), 1) as 'สัดส่วน (%)'
            FROM land_plots lp GROUP BY lp.land_use_type ORDER BY COUNT(*) DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getCaseStatus(PDO $db, array $f): array
    {
        $where = "1=1";
        $params = [];
        if (!empty($f['status'])) {
            $where .= " AND c.status = :st";
            $params['st'] = $f['status'];
        }
        if (!empty($f['case_type'])) {
            $where .= " AND c.case_type = :ct";
            $params['ct'] = $f['case_type'];
        }
        if (!empty($f['date_from'])) {
            $where .= " AND c.created_at >= :df";
            $params['df'] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where .= " AND c.created_at <= :dt";
            $params['dt'] = $f['date_to'] . ' 23:59:59';
        }

        $stmt = $db->prepare("SELECT ROW_NUMBER() OVER (ORDER BY c.case_id) as 'ลำดับ',
            c.case_number as 'เลขที่',
            CASE c.case_type WHEN 'complaint' THEN 'ร้องเรียน' WHEN 'request_use' THEN 'ขอใช้พื้นที่' WHEN 'trespass_report' THEN 'รายงานบุกรุก' WHEN 'renewal' THEN 'ขอต่ออายุ' ELSE 'อื่นๆ' END as 'ประเภท',
            c.subject as 'เรื่อง',
            CONCAT(IFNULL(v.prefix,''), v.first_name, ' ', v.last_name) as 'ผู้ร้อง',
            CASE c.priority WHEN 'high' THEN 'สูง' WHEN 'medium' THEN 'กลาง' ELSE 'ต่ำ' END as 'ความเร่งด่วน',
            CASE c.status WHEN 'new' THEN 'ใหม่' WHEN 'in_progress' THEN 'กำลังดำเนินการ' WHEN 'awaiting_approval' THEN 'รอผลอนุมัติ' WHEN 'closed' THEN 'ปิดเรื่อง' ELSE 'ยกเลิก' END as 'สถานะ',
            IFNULL(u.full_name,'ยังไม่มอบหมาย') as 'ผู้รับผิดชอบ',
            DATE_FORMAT(c.created_at, '%d/%m/%Y') as 'วันที่สร้าง'
            FROM cases c 
            JOIN villagers v ON c.villager_id = v.villager_id
            LEFT JOIN users u ON c.assigned_to = u.user_id
            WHERE $where ORDER BY c.case_id DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getCaseDetail(PDO $db, array $f): array
    {
        $where = "1=1";
        $params = [];
        if (!empty($f['case_id'])) {
            $where .= " AND c.case_id = :cid";
            $params['cid'] = $f['case_id'];
        }

        $stmt = $db->prepare("SELECT c.*, 
            v.prefix, v.first_name, v.last_name, v.id_card_number, v.phone as villager_phone,
            v.address, v.village_name,
            lp.plot_code, lp.area_rai, lp.area_ngan, lp.area_sqwa,
            u1.full_name as assigned_name, u2.full_name as creator_name
            FROM cases c
            JOIN villagers v ON c.villager_id = v.villager_id
            LEFT JOIN land_plots lp ON c.plot_id = lp.plot_id
            LEFT JOIN users u1 ON c.assigned_to = u1.user_id
            LEFT JOIN users u2 ON c.created_by = u2.user_id
            WHERE $where ORDER BY c.case_id DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getExecutiveSummary(PDO $db, array $f): array
    {
        // Returns summary statistics as key-value pairs
        $stats = [];

        $stats['villager_count'] = $db->query("SELECT COUNT(*) FROM villagers")->fetchColumn();
        $stats['plot_count'] = $db->query("SELECT COUNT(*) FROM land_plots")->fetchColumn();
        $stats['total_area'] = $db->query("SELECT IFNULL(SUM(area_rai),0) FROM land_plots")->fetchColumn();
        $stats['case_count'] = $db->query("SELECT COUNT(*) FROM cases")->fetchColumn();
        $stats['open_cases'] = $db->query("SELECT COUNT(*) FROM cases WHERE status NOT IN ('closed','rejected')")->fetchColumn();

        // Plot status breakdown
        $stats['plot_status'] = $db->query("SELECT status, COUNT(*) as cnt FROM land_plots GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);

        // Land use breakdown
        $stats['land_use'] = $db->query("SELECT land_use_type, COUNT(*) as cnt, SUM(area_rai) as total_rai FROM land_plots GROUP BY land_use_type")->fetchAll(PDO::FETCH_ASSOC);

        // Case type breakdown
        $stats['case_types'] = $db->query("SELECT case_type, COUNT(*) as cnt FROM cases GROUP BY case_type")->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    private static function getDocumentList(PDO $db, array $f): array
    {
        $stmt = $db->query("SELECT ROW_NUMBER() OVER (ORDER BY v.villager_id) as 'ลำดับ',
            v.id_card_number as 'เลขบัตร',
            CONCAT(IFNULL(v.prefix,''), v.first_name, ' ', v.last_name) as 'ชื่อ-นามสกุล',
            COUNT(DISTINCT lp.plot_id) as 'จำนวนแปลง',
            SUM(CASE WHEN lp.has_document = 1 THEN 1 ELSE 0 END) as 'มีเอกสารสิทธิ์',
            SUM(CASE WHEN lp.has_document = 0 THEN 1 ELSE 0 END) as 'ไม่มีเอกสาร',
            GROUP_CONCAT(DISTINCT lp.document_type SEPARATOR ', ') as 'ประเภทเอกสาร'
            FROM villagers v
            LEFT JOIN land_plots lp ON v.villager_id = lp.villager_id
            GROUP BY v.villager_id ORDER BY v.villager_id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getActivityLog(PDO $db, array $f): array
    {
        $where = "1=1";
        $params = [];
        if (!empty($f['date_from'])) {
            $where .= " AND al.created_at >= :df";
            $params['df'] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where .= " AND al.created_at <= :dt";
            $params['dt'] = $f['date_to'] . ' 23:59:59';
        }

        $stmt = $db->prepare("SELECT ROW_NUMBER() OVER (ORDER BY al.created_at DESC) as 'ลำดับ',
            DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i') as 'วันเวลา',
            u.full_name as 'ผู้ดำเนินการ',
            CASE al.action WHEN 'create' THEN 'เพิ่มข้อมูล' WHEN 'update' THEN 'แก้ไข' WHEN 'delete' THEN 'ลบ' WHEN 'export' THEN 'ส่งออก' WHEN 'login' THEN 'เข้าสู่ระบบ' WHEN 'logout' THEN 'ออกจากระบบ' END as 'การกระทำ',
            al.table_name as 'ตาราง',
            IFNULL(al.description,'') as 'รายละเอียด',
            al.ip_address as 'IP'
            FROM activity_logs al
            JOIN users u ON al.user_id = u.user_id
            WHERE $where ORDER BY al.created_at DESC LIMIT 500");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
