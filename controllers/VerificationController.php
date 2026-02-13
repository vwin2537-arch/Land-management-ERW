<?php
/**
 * VerificationController — ระบบตรวจสอบสิทธิ/จัดสรรที่ดิน
 * Workflow: ค้นหาเลขบัตร → ดูเนื้อที่รวม → จัดสรร → ออกเอกสาร
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class VerificationController
{
    /**
     * ค้นหาราษฎรจากเลขบัตรประชาชน
     */
    public static function searchByIdCard(string $idCard): ?array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM villagers WHERE id_card_number = :idc LIMIT 1");
        $stmt->execute(['idc' => $idCard]);
        $villager = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$villager) return null;

        $villager['plots'] = self::getPlots($villager['villager_id']);
        $villager['area_summary'] = self::calcAreaSummary($villager['plots']);
        $villager['household_members'] = self::getHouseholdMembers($villager['villager_id']);
        return $villager;
    }

    /**
     * ดึงข้อมูลราษฎรจาก villager_id
     */
    public static function getVillagerById(int $id): ?array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM villagers WHERE villager_id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $villager = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$villager) return null;

        $villager['plots'] = self::getPlots($id);
        $villager['area_summary'] = self::calcAreaSummary($villager['plots']);
        $villager['household_members'] = self::getHouseholdMembers($id);
        return $villager;
    }

    /**
     * ดึงแปลงที่ดินทั้งหมดของราษฎร
     */
    public static function getPlots(int $villagerId): array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM land_plots WHERE villager_id = :vid ORDER BY num_apar, plot_id");
        $stmt->execute(['vid' => $villagerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงสมาชิกครอบครัว
     */
    public static function getHouseholdMembers(int $villagerId): array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM household_members WHERE villager_id = :vid ORDER BY member_id");
        $stmt->execute(['vid' => $villagerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * คำนวณเนื้อที่รวม (ไร่) จากทุกแปลง
     */
    public static function calcAreaSummary(array $plots): array
    {
        $totalRai = 0;
        $totalNgan = 0;
        $totalSqwa = 0;

        foreach ($plots as $p) {
            $totalRai += (int)($p['area_rai'] ?? 0);
            $totalNgan += (int)($p['area_ngan'] ?? 0);
            $totalSqwa += (int)($p['area_sqwa'] ?? 0);
        }

        // Normalize
        $totalNgan += intdiv($totalSqwa, 100);
        $totalSqwa = $totalSqwa % 100;
        $totalRai += intdiv($totalNgan, 4);
        $totalNgan = $totalNgan % 4;

        $totalInRai = $totalRai + ($totalNgan / 4) + ($totalSqwa / 400);

        // สถานะเกณฑ์
        if ($totalInRai <= 20) {
            $status = 'within_20';
            $statusLabel = 'ไม่เกิน 20 ไร่ — ปกติ';
        } elseif ($totalInRai <= 40) {
            $status = 'over_20';
            $statusLabel = 'เกิน 20 ไร่ — ต้องจัดสรรให้ครัวเรือน/ทายาท';
        } else {
            $status = 'over_40';
            $statusLabel = 'เกิน 40 ไร่ — ส่วนเกินเข้า ม.19';
        }

        return [
            'rai' => $totalRai,
            'ngan' => $totalNgan,
            'sqwa' => $totalSqwa,
            'total_in_rai' => round($totalInRai, 4),
            'plot_count' => count($plots),
            'status' => $status,
            'status_label' => $statusLabel,
        ];
    }

    /**
     * แปลง ไร่-งาน-ตร.วา เป็นไร่ (ทศนิยม)
     */
    public static function toRai(int $rai, int $ngan, int $sqwa): float
    {
        return $rai + ($ngan / 4) + ($sqwa / 400);
    }

    /**
     * ดึง allocations ที่บันทึกไว้แล้ว
     */
    public static function getAllocations(int $villagerId): array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT pa.*, hm.prefix as m_prefix, hm.first_name as m_first_name, 
                              hm.last_name as m_last_name, hm.relationship as m_relationship
                              FROM plot_allocations pa
                              LEFT JOIN household_members hm ON pa.member_id = hm.member_id
                              WHERE pa.villager_id = :vid ORDER BY pa.plot_id, pa.id");
        $stmt->execute(['vid' => $villagerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * บันทึกการจัดสรรที่ดิน (รองรับ partial/split)
     *
     * $allocations = [
     *   ['plot_id' => 1, 'type' => 'owner', 'area' => 20.0, 'member_id' => null],
     *   ['plot_id' => 1, 'type' => 'heir',  'area' => 20.0, 'member_id' => 5],
     *   ['plot_id' => 1, 'type' => 'section19', 'area' => 27.41, 'member_id' => null],
     * ]
     */
    public static function saveAllocation(int $villagerId, array $allocations): bool
    {
        $db = getDB();
        try {
            $db->beginTransaction();

            // ลบ allocations เดิม
            $db->prepare("DELETE FROM plot_allocations WHERE villager_id = :vid")
               ->execute(['vid' => $villagerId]);

            // ลบแปลงแบ่งเดิม (prefix 3/4) ที่สร้างจากผู้ครอบครองนี้
            $db->prepare("DELETE FROM land_plots WHERE parent_plot_id IS NOT NULL 
                AND parent_plot_id IN (SELECT plot_id FROM (SELECT plot_id FROM land_plots WHERE villager_id = :vid AND parent_plot_id IS NULL) tmp)")
               ->execute(['vid' => $villagerId]);

            // บันทึก allocations ใหม่
            $stmtInsert = $db->prepare("INSERT INTO plot_allocations 
                (plot_id, villager_id, allocation_type, allocated_area_rai, member_id)
                VALUES (:pid, :vid, :atype, :area, :mid)");

            foreach ($allocations as $a) {
                if (($a['area'] ?? 0) <= 0) continue;
                $stmtInsert->execute([
                    'pid'   => $a['plot_id'],
                    'vid'   => $villagerId,
                    'atype' => $a['type'],
                    'area'  => $a['area'],
                    'mid'   => $a['member_id'] ?? null,
                ]);
            }

            // อัพเดท land_plots.allocation_type (summary)
            $plotIds = array_unique(array_column($allocations, 'plot_id'));
            foreach ($plotIds as $pid) {
                $plotAllocs = array_filter($allocations, fn($a) => (int)$a['plot_id'] === (int)$pid && ($a['area'] ?? 0) > 0);
                $types = array_unique(array_column($plotAllocs, 'type'));
                $primaryType = count($types) > 1 ? 'split' : ($types[0] ?? 'unallocated');
                $db->prepare("UPDATE land_plots SET allocation_type = :atype WHERE plot_id = :pid AND villager_id = :vid")
                   ->execute(['atype' => $primaryType, 'pid' => $pid, 'vid' => $villagerId]);
            }

            // ===== สร้างแปลงใหม่ prefix 3 (heir) และ 4 (section19) อัตโนมัติ =====
            foreach ($allocations as $a) {
                $area = (float)($a['area'] ?? 0);
                $type = $a['type'] ?? '';
                if ($area <= 0 || !in_array($type, ['heir', 'section19'])) continue;

                $parentPlotId = (int)$a['plot_id'];

                // ดึงข้อมูลแปลงต้นทาง
                $stmtParent = $db->prepare("SELECT * FROM land_plots WHERE plot_id = :pid LIMIT 1");
                $stmtParent->execute(['pid' => $parentPlotId]);
                $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);
                if (!$parent) continue;

                // กำหนด prefix: 3 = heir (แบ่งครัวเรือน), 4 = section19
                $prefix = ($type === 'section19') ? '4' : '3';

                // หาเลขลำดับถัดไป
                $stmtMax = $db->prepare("SELECT MAX(CAST(num_apar AS UNSIGNED)) as mx FROM land_plots WHERE num_apar LIKE :pfx");
                $stmtMax->execute(['pfx' => $prefix . '%']);
                $maxNum = (int)($stmtMax->fetch(PDO::FETCH_ASSOC)['mx'] ?? 0);
                $nextNum = $maxNum > 0 ? $maxNum + 1 : (int)($prefix . '0001');
                $newNumApar = (string)$nextNum;

                // plot_code ใหม่
                $newPlotCode = 'SUB-' . $prefix . '-' . str_pad($nextNum % 10000, 4, '0', STR_PAD_LEFT);

                // แปลง area (ทศนิยมไร่) เป็น ไร่-งาน-ตร.วา
                $rai = floor($area);
                $remainNgan = ($area - $rai) * 4;
                $ngan = floor($remainNgan);
                $sqwa = round(($remainNgan - $ngan) * 100, 0);

                // กำหนดผู้ครอบครองแปลงใหม่
                $assigneeId = $villagerId;
                $memberId = $a['member_id'] ?? null;
                if ($memberId && $type === 'heir') {
                    // ลองหาว่า member มี villager_id ไหม
                    $stmtMember = $db->prepare("SELECT hm.id_card_number FROM household_members hm WHERE hm.member_id = :mid LIMIT 1");
                    $stmtMember->execute(['mid' => $memberId]);
                    $memberRow = $stmtMember->fetch(PDO::FETCH_ASSOC);
                    if ($memberRow && !empty($memberRow['id_card_number'])) {
                        $stmtVil = $db->prepare("SELECT villager_id FROM villagers WHERE id_card_number = :idc LIMIT 1");
                        $stmtVil->execute(['idc' => $memberRow['id_card_number']]);
                        $vilRow = $stmtVil->fetch(PDO::FETCH_ASSOC);
                        if ($vilRow) $assigneeId = (int)$vilRow['villager_id'];
                    }
                }

                $noteText = $type === 'section19'
                    ? 'ม.19 — ส่วนเกินจากแปลง ' . ($parent['num_apar'] ?? $parentPlotId)
                    : 'แบ่งครัวเรือนจากแปลง ' . ($parent['num_apar'] ?? $parentPlotId);

                $stmtNew = $db->prepare("INSERT INTO land_plots 
                    (plot_code, villager_id, parent_plot_id, allocation_type,
                     park_name, zone, area_rai, area_ngan, area_sqwa,
                     land_use_type, status, notes,
                     code_dnp, apar_code, apar_no, num_apar, spar_code, ban_e,
                     num_spar, spar_no, par_ban, par_moo, par_tam, par_amp, par_prov, ptype)
                    VALUES 
                    (:plot_code, :villager_id, :parent_plot_id, :allocation_type,
                     :park_name, :zone, :area_rai, :area_ngan, :area_sqwa,
                     :land_use_type, :status, :notes,
                     :code_dnp, :apar_code, :apar_no, :num_apar, :spar_code, :ban_e,
                     :num_spar, :spar_no, :par_ban, :par_moo, :par_tam, :par_amp, :par_prov, :ptype)");

                $stmtNew->execute([
                    'plot_code' => $newPlotCode,
                    'villager_id' => $assigneeId,
                    'parent_plot_id' => $parentPlotId,
                    'allocation_type' => $type,
                    'park_name' => $parent['park_name'],
                    'zone' => $parent['zone'],
                    'area_rai' => $rai,
                    'area_ngan' => $ngan,
                    'area_sqwa' => $sqwa,
                    'land_use_type' => $parent['land_use_type'] ?? 'agriculture',
                    'status' => 'surveyed',
                    'notes' => $noteText,
                    'code_dnp' => $parent['code_dnp'],
                    'apar_code' => $parent['apar_code'],
                    'apar_no' => $parent['apar_no'],
                    'num_apar' => $newNumApar,
                    'spar_code' => $parent['spar_code'],
                    'ban_e' => $parent['ban_e'],
                    'num_spar' => $parent['num_spar'],
                    'spar_no' => $parent['spar_no'],
                    'par_ban' => $parent['par_ban'],
                    'par_moo' => $parent['par_moo'],
                    'par_tam' => $parent['par_tam'],
                    'par_amp' => $parent['par_amp'],
                    'par_prov' => $parent['par_prov'],
                    'ptype' => $parent['ptype'],
                ]);
            }

            // อัพเดทสถานะ villager
            $db->prepare("UPDATE villagers SET 
                verification_status = 'verified',
                verified_at = NOW(),
                verified_by = :uid
                WHERE villager_id = :vid")
               ->execute(['uid' => $_SESSION['user_id'] ?? null, 'vid' => $villagerId]);

            $db->commit();
            return true;
        } catch (PDOException $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * ยืนยัน ≤20 ไร่ (ผ่านทันที)
     */
    public static function verifySimple(int $villagerId): bool
    {
        $db = getDB();
        try {
            $db->beginTransaction();

            // ตั้งทุกแปลงเป็น owner
            $db->prepare("UPDATE land_plots SET allocation_type = 'owner' WHERE villager_id = :vid")
               ->execute(['vid' => $villagerId]);

            // ตั้ง qualification ผ่าน
            $db->prepare("UPDATE villagers SET 
                verification_status = 'verified',
                qualification_status = 'passed',
                verified_at = NOW(),
                verified_by = :uid
                WHERE villager_id = :vid")
               ->execute(['uid' => $_SESSION['user_id'] ?? null, 'vid' => $villagerId]);

            $db->commit();
            return true;
        } catch (PDOException $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * บันทึกสมาชิกครัวเรือน (ทายาท) ใหม่
     */
    public static function addHouseholdMember(int $villagerId, array $data): int
    {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO household_members 
            (villager_id, prefix, first_name, last_name, id_card_number, relationship, notes)
            VALUES (:vid, :prefix, :fname, :lname, :idc, :rel, :notes)");
        $stmt->execute([
            'vid' => $villagerId,
            'prefix' => $data['prefix'] ?? '',
            'fname' => $data['first_name'],
            'lname' => $data['last_name'],
            'idc' => $data['id_card_number'] ?? '',
            'rel' => $data['relationship'] ?? '',
            'notes' => $data['notes'] ?? '',
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * สถิติรวม — จำนวนราษฎรที่ตรวจแล้ว/ยังไม่ตรวจ
     */
    public static function getStats(): array
    {
        $db = getDB();
        $total = $db->query("SELECT COUNT(*) FROM villagers")->fetchColumn();
        $verified = $db->query("SELECT COUNT(*) FROM villagers WHERE verification_status = 'verified'")->fetchColumn();
        $pending = $total - $verified;
        return compact('total', 'verified', 'pending');
    }
}
