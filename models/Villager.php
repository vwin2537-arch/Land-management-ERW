<?php
/**
 * Model: Villager — ทะเบียนราษฎร
 */

require_once __DIR__ . '/../config/database.php';

class Villager
{

    /**
     * Get all villagers (with optional search)
     */
    public static function getAll(string $search = '', int $limit = 20, int $offset = 0): array
    {
        $db = getDB();
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE id_card_number LIKE :s1 OR first_name LIKE :s2 OR last_name LIKE :s3 OR village_name LIKE :s4";
            $params = ['s1' => "%$search%", 's2' => "%$search%", 's3' => "%$search%", 's4' => "%$search%"];
        }

        $stmt = $db->prepare("SELECT v.*, 
                              (SELECT COUNT(*) FROM land_plots lp WHERE lp.villager_id = v.villager_id) as plot_count
                              FROM villagers v $where ORDER BY v.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count villagers
     */
    public static function count(string $search = ''): int
    {
        $db = getDB();
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE id_card_number LIKE :s1 OR first_name LIKE :s2 OR last_name LIKE :s3 OR village_name LIKE :s4";
            $params = ['s1' => "%$search%", 's2' => "%$search%", 's3' => "%$search%", 's4' => "%$search%"];
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM villagers $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Find by ID
     */
    public static function find(int $id): ?array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM villagers WHERE villager_id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create new villager
     */
    public static function create(array $data): int
    {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO villagers 
            (id_card_number, prefix, first_name, last_name, birth_date, phone, address, 
             village_no, village_name, sub_district, district, province, photo_path, notes, created_by)
            VALUES 
            (:id_card_number, :prefix, :first_name, :last_name, :birth_date, :phone, :address,
             :village_no, :village_name, :sub_district, :district, :province, :photo_path, :notes, :created_by)");

        $stmt->execute([
            'id_card_number' => $data['id_card_number'],
            'prefix' => $data['prefix'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_date' => $data['birth_date'] ?: null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'village_no' => $data['village_no'] ?? null,
            'village_name' => $data['village_name'] ?? null,
            'sub_district' => $data['sub_district'] ?? null,
            'district' => $data['district'] ?? null,
            'province' => $data['province'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update villager
     */
    public static function update(int $id, array $data): bool
    {
        $db = getDB();
        $stmt = $db->prepare("UPDATE villagers SET
            id_card_number = :id_card_number, prefix = :prefix, 
            first_name = :first_name, last_name = :last_name, 
            birth_date = :birth_date, phone = :phone, address = :address,
            village_no = :village_no, village_name = :village_name,
            sub_district = :sub_district, district = :district, province = :province,
            photo_path = :photo_path, notes = :notes
            WHERE villager_id = :id");

        return $stmt->execute([
            'id' => $id,
            'id_card_number' => $data['id_card_number'],
            'prefix' => $data['prefix'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_date' => $data['birth_date'] ?: null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'village_no' => $data['village_no'] ?? null,
            'village_name' => $data['village_name'] ?? null,
            'sub_district' => $data['sub_district'] ?? null,
            'district' => $data['district'] ?? null,
            'province' => $data['province'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Delete villager
     */
    public static function delete(int $id): bool
    {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM villagers WHERE villager_id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get all for dropdown
     */
    public static function getAllForSelect(): array
    {
        $db = getDB();
        return $db->query("SELECT villager_id, prefix, first_name, last_name, id_card_number 
                           FROM villagers ORDER BY first_name")->fetchAll();
    }
}
