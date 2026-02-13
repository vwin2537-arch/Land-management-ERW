<?php
/**
 * SubdivisionController — ระบบแบ่งแปลงที่ดิน
 * จัดการแปลงที่ดินเกิน 20 ไร่ (prefix 3) และเกิน 40 ไร่ (prefix 4)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class SubdivisionController
{
    /**
     * ดึงรายชื่อผู้ครอบครองที่รวมเนื้อที่ >20 ไร่ (ต้องแบ่งแปลง)
     */
    public static function getQualifyingVillagers(string $filter = 'all'): array
    {
        $db = getDB();
        $having = 'HAVING total_rai > 20';
        if ($filter === 'over40') {
            $having = 'HAVING total_rai > 40';
        } elseif ($filter === 'over20') {
            $having = 'HAVING total_rai > 20 AND total_rai <= 40';
        }

        $stmt = $db->query("
            SELECT v.villager_id, v.prefix, v.first_name, v.last_name, v.id_card_number,
                   COUNT(lp.plot_id) as plot_count,
                   ROUND(SUM(lp.area_rai + lp.area_ngan/4 + lp.area_sqwa/400), 2) as total_rai,
                   GROUP_CONCAT(DISTINCT lp.apar_no ORDER BY lp.apar_no SEPARATOR ', ') as zones,
                   (SELECT COUNT(*) FROM land_plots sub WHERE sub.parent_plot_id IS NOT NULL 
                    AND sub.villager_id = v.villager_id) as subdivided_count
            FROM land_plots lp
            JOIN villagers v ON lp.villager_id = v.villager_id
            WHERE lp.parent_plot_id IS NULL
            GROUP BY v.villager_id
            $having
            ORDER BY total_rai DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงข้อมูลผู้ครอบครองพร้อมแปลงทั้งหมด (สำหรับหน้าแบ่งแปลง)
     */
    public static function getVillagerDetail(int $villagerId): ?array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM villagers WHERE villager_id = :vid");
        $stmt->execute(['vid' => $villagerId]);
        $villager = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$villager) return null;

        // แปลงต้นฉบับ (parent_plot_id IS NULL, ไม่ใช่แปลงที่แบ่งแล้ว)
        $stmt2 = $db->prepare("SELECT * FROM land_plots WHERE villager_id = :vid AND parent_plot_id IS NULL ORDER BY num_apar, plot_id");
        $stmt2->execute(['vid' => $villagerId]);
        $villager['original_plots'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // แปลงที่แบ่งแล้ว (prefix 3, 4)
        $stmt3 = $db->prepare("SELECT lp.*, 
            v2.prefix as assigned_prefix, v2.first_name as assigned_first_name, v2.last_name as assigned_last_name
            FROM land_plots lp
            LEFT JOIN villagers v2 ON lp.villager_id = v2.villager_id
            WHERE lp.parent_plot_id IN (SELECT plot_id FROM land_plots WHERE villager_id = :vid AND parent_plot_id IS NULL)
            ORDER BY lp.num_apar, lp.plot_id");
        $stmt3->execute(['vid' => $villagerId]);
        $villager['subdivided_plots'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        // สมาชิกครัวเรือน
        $stmt4 = $db->prepare("SELECT * FROM household_members WHERE villager_id = :vid ORDER BY member_id");
        $stmt4->execute(['vid' => $villagerId]);
        $villager['household_members'] = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        // คำนวณสรุป
        $totalRai = 0;
        foreach ($villager['original_plots'] as $p) {
            $totalRai += $p['area_rai'] + $p['area_ngan'] / 4 + $p['area_sqwa'] / 400;
        }
        $villager['total_rai'] = round($totalRai, 2);

        $subdividedRai = 0;
        foreach ($villager['subdivided_plots'] as $p) {
            $subdividedRai += $p['area_rai'] + $p['area_ngan'] / 4 + $p['area_sqwa'] / 400;
        }
        $villager['subdivided_rai'] = round($subdividedRai, 2);

        return $villager;
    }

}
