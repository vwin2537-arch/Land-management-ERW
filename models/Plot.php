<?php
/**
 * Model: Plot — แปลงที่ดินทำกิน
 */

require_once __DIR__ . '/../config/database.php';

class Plot
{

    /**
     * Find by ID with owner info
     */
    public static function find(int $id): ?array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT lp.*, v.prefix, v.first_name, v.last_name, v.id_card_number,
                                     u.full_name as surveyor_name
                              FROM land_plots lp
                              JOIN villagers v ON lp.villager_id = v.villager_id
                              LEFT JOIN users u ON lp.surveyed_by = u.user_id
                              WHERE lp.plot_id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create new plot
     */
    public static function create(array $data): int
    {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO land_plots 
            (plot_code, villager_id, park_name, zone, area_rai, area_ngan, area_sqwa,
             land_use_type, crop_type, latitude, longitude, polygon_coords,
             occupation_since, has_document, document_type, status, survey_date, 
             surveyed_by, plot_image_path, notes,
             code_dnp, apar_code, apar_no, num_apar, spar_code, ban_e, perimeter, ban_type,
             num_spar, spar_no, par_ban, par_moo, par_tam, par_amp, par_prov, ptype, target_fid, data_issues)
            VALUES 
            (:plot_code, :villager_id, :park_name, :zone, :area_rai, :area_ngan, :area_sqwa,
             :land_use_type, :crop_type, :latitude, :longitude, :polygon_coords,
             :occupation_since, :has_document, :document_type, :status, :survey_date,
             :surveyed_by, :plot_image_path, :notes,
             :code_dnp, :apar_code, :apar_no, :num_apar, :spar_code, :ban_e, :perimeter, :ban_type,
             :num_spar, :spar_no, :par_ban, :par_moo, :par_tam, :par_amp, :par_prov, :ptype, :target_fid, :data_issues)");

        $stmt->execute([
            'plot_code' => $data['plot_code'],
            'villager_id' => $data['villager_id'],
            'park_name' => $data['park_name'] ?? null,
            'zone' => $data['zone'] ?? null,
            'area_rai' => $data['area_rai'] ?? 0,
            'area_ngan' => $data['area_ngan'] ?? 0,
            'area_sqwa' => $data['area_sqwa'] ?? 0,
            'land_use_type' => $data['land_use_type'] ?? 'agriculture',
            'crop_type' => $data['crop_type'] ?? null,
            'latitude' => $data['latitude'] ?: null,
            'longitude' => $data['longitude'] ?: null,
            'polygon_coords' => $data['polygon_coords'] ?? null,
            'occupation_since' => $data['occupation_since'] ?: null,
            'has_document' => $data['has_document'] ?? 0,
            'document_type' => $data['document_type'] ?? null,
            'status' => $data['status'] ?? 'pending_review',
            'survey_date' => $data['survey_date'] ?: null,
            'surveyed_by' => $_SESSION['user_id'] ?? null,
            'plot_image_path' => $data['plot_image_path'] ?? null,
            'notes' => $data['notes'] ?? null,
            'code_dnp' => $data['code_dnp'] ?? null,
            'apar_code' => $data['apar_code'] ?? null,
            'apar_no' => $data['apar_no'] ?? null,
            'num_apar' => $data['num_apar'] ?? null,
            'spar_code' => $data['spar_code'] ?? null,
            'ban_e' => $data['ban_e'] ?? null,
            'perimeter' => $data['perimeter'] ?? null,
            'ban_type' => $data['ban_type'] ?? null,
            'num_spar' => $data['num_spar'] ?? null,
            'spar_no' => $data['spar_no'] ?? null,
            'par_ban' => $data['par_ban'] ?? null,
            'par_moo' => $data['par_moo'] ?? null,
            'par_tam' => $data['par_tam'] ?? null,
            'par_amp' => $data['par_amp'] ?? null,
            'par_prov' => $data['par_prov'] ?? null,
            'ptype' => $data['ptype'] ?? null,
            'target_fid' => $data['target_fid'] ?? null,
            'data_issues' => $data['data_issues'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update plot
     */
    public static function update(int $id, array $data): bool
    {
        $db = getDB();
        $stmt = $db->prepare("UPDATE land_plots SET
            plot_code = :plot_code, villager_id = :villager_id, park_name = :park_name,
            zone = :zone, area_rai = :area_rai, area_ngan = :area_ngan, area_sqwa = :area_sqwa,
            land_use_type = :land_use_type, crop_type = :crop_type,
            latitude = :latitude, longitude = :longitude, polygon_coords = :polygon_coords,
            occupation_since = :occupation_since, has_document = :has_document,
            document_type = :document_type, status = :status, survey_date = :survey_date,
            plot_image_path = :plot_image_path, notes = :notes,
            code_dnp = :code_dnp, apar_code = :apar_code, apar_no = :apar_no,
            num_apar = :num_apar, spar_code = :spar_code,
            perimeter = :perimeter, ban_type = :ban_type,
            num_spar = :num_spar, spar_no = :spar_no, par_ban = :par_ban,
            par_moo = :par_moo, par_tam = :par_tam, par_amp = :par_amp,
            par_prov = :par_prov, ptype = :ptype, target_fid = :target_fid,
            data_issues = :data_issues
            WHERE plot_id = :id");

        return $stmt->execute([
            'id' => $id,
            'plot_code' => $data['plot_code'],
            'villager_id' => $data['villager_id'],
            'park_name' => $data['park_name'] ?? null,
            'zone' => $data['zone'] ?? null,
            'area_rai' => $data['area_rai'] ?? 0,
            'area_ngan' => $data['area_ngan'] ?? 0,
            'area_sqwa' => $data['area_sqwa'] ?? 0,
            'land_use_type' => $data['land_use_type'] ?? 'agriculture',
            'crop_type' => $data['crop_type'] ?? null,
            'latitude' => $data['latitude'] ?: null,
            'longitude' => $data['longitude'] ?: null,
            'polygon_coords' => $data['polygon_coords'] ?? null,
            'occupation_since' => $data['occupation_since'] ?: null,
            'has_document' => $data['has_document'] ?? 0,
            'document_type' => $data['document_type'] ?? null,
            'status' => $data['status'] ?? 'pending_review',
            'survey_date' => $data['survey_date'] ?: null,
            'plot_image_path' => $data['plot_image_path'] ?? null,
            'notes' => $data['notes'] ?? null,
            'code_dnp' => $data['code_dnp'] ?? null,
            'apar_code' => $data['apar_code'] ?? null,
            'apar_no' => $data['apar_no'] ?? null,
            'num_apar' => $data['num_apar'] ?? null,
            'spar_code' => $data['spar_code'] ?? null,
            'ban_e' => $data['ban_e'] ?? null,
            'perimeter' => $data['perimeter'] ?? 0,
            'ban_type' => $data['ban_type'] ?? null,
            'num_spar' => $data['num_spar'] ?? null,
            'spar_no' => $data['spar_no'] ?? null,
            'par_ban' => $data['par_ban'] ?? null,
            'par_moo' => $data['par_moo'] ?? null,
            'par_tam' => $data['par_tam'] ?? null,
            'par_amp' => $data['par_amp'] ?? null,
            'par_prov' => $data['par_prov'] ?? null,
            'ptype' => $data['ptype'] ?? null,
            'target_fid' => $data['target_fid'] ?? null,
            'data_issues' => $data['data_issues'] ?? null,
        ]);
    }

    /**
     * Delete plot
     */
    public static function delete(int $id): bool
    {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM land_plots WHERE plot_id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get plots by villager
     */
    public static function getByVillager(int $villagerId): array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM land_plots WHERE villager_id = :vid ORDER BY plot_code");
        $stmt->execute(['vid' => $villagerId]);
        return $stmt->fetchAll();
    }

    /**
     * Generate next plot code
     */
    public static function generateCode(string $prefix = 'NP'): string
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT plot_code FROM land_plots WHERE plot_code LIKE :prefix ORDER BY plot_id DESC LIMIT 1");
        $stmt->execute(['prefix' => "$prefix-%"]);
        $last = $stmt->fetchColumn();

        if ($last) {
            $num = (int) substr($last, strlen($prefix) + 1);
            return $prefix . '-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
        }
        return $prefix . '-001';
    }
}
