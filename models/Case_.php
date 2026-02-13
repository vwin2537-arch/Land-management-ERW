<?php
/**
 * Model: Case_ — คำร้อง/เรื่องร้องเรียน
 * (ใช้ชื่อ Case_ เพราะ "Case" เป็น reserved keyword ใน PHP)
 */

require_once __DIR__ . '/../config/database.php';

class Case_
{

    /**
     * Find by ID with related info
     */
    public static function find(int $id): ?array
    {
        $db = getDB();
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
            WHERE c.case_id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create new case — auto-generate case number
     */
    public static function create(array $data): int
    {
        $db = getDB();

        // Generate case number: CS-พ.ศ.-ลำดับ
        $year = date('Y') + 543;
        $countStmt = $db->query("SELECT COUNT(*) + 1 FROM cases WHERE YEAR(created_at) = YEAR(CURDATE())");
        $seq = str_pad($countStmt->fetchColumn(), 4, '0', STR_PAD_LEFT);
        $caseNumber = "CS-$year-$seq";

        $stmt = $db->prepare("INSERT INTO cases 
            (case_number, case_type, subject, description, villager_id, plot_id, priority, status, assigned_to, created_by)
            VALUES (:case_number, :case_type, :subject, :description, :villager_id, :plot_id, :priority, :status, :assigned_to, :created_by)");

        $stmt->execute([
            'case_number' => $caseNumber,
            'case_type' => $data['case_type'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'villager_id' => $data['villager_id'],
            'plot_id' => $data['plot_id'] ?: null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'new',
            'assigned_to' => $data['assigned_to'] ?: null,
            'created_by' => $_SESSION['user_id'],
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update case
     */
    public static function update(int $id, array $data): bool
    {
        $db = getDB();
        $stmt = $db->prepare("UPDATE cases SET
            case_type = :case_type, subject = :subject, description = :description,
            villager_id = :villager_id, plot_id = :plot_id, priority = :priority,
            status = :status, assigned_to = :assigned_to, resolution = :resolution
            WHERE case_id = :id");

        return $stmt->execute([
            'id' => $id,
            'case_type' => $data['case_type'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'villager_id' => $data['villager_id'],
            'plot_id' => $data['plot_id'] ?: null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'new',
            'assigned_to' => $data['assigned_to'] ?: null,
            'resolution' => $data['resolution'] ?? null,
        ]);
    }

    /**
     * Delete case
     */
    public static function delete(int $id): bool
    {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM cases WHERE case_id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get case number by ID
     */
    public static function getCaseNumber(int $id): ?string
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT case_number FROM cases WHERE case_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() ?: null;
    }
}
