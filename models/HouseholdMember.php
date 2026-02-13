<?php
/**
 * Model: HouseholdMember — สมาชิกครอบครัว/ครัวเรือน (อส.6-2)
 */

require_once __DIR__ . '/../config/database.php';

class HouseholdMember
{

    /**
     * Get members by villager ID
     */
    public static function getByVillager(int $villagerId): array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM household_members WHERE villager_id = :vid ORDER BY member_id");
        $stmt->execute(['vid' => $villagerId]);
        return $stmt->fetchAll();
    }

    /**
     * Create new member
     */
    public static function create(array $data): int
    {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO household_members 
            (villager_id, prefix, first_name, last_name, id_card_number, relationship, notes)
            VALUES (:villager_id, :prefix, :first_name, :last_name, :id_card_number, :relationship, :notes)");

        $stmt->execute([
            'villager_id' => $data['villager_id'],
            'prefix' => $data['prefix'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'id_card_number' => $data['id_card_number'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Delete member
     */
    public static function delete(int $id): bool
    {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM household_members WHERE member_id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Count members by villager
     */
    public static function countByVillager(int $villagerId): int
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM household_members WHERE villager_id = :vid");
        $stmt->execute(['vid' => $villagerId]);
        return (int) $stmt->fetchColumn();
    }
}
