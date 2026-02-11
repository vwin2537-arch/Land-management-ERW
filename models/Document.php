<?php
/**
 * Model: Document — คลังเอกสาร
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Document
{

    /**
     * Get documents for a related entity
     */
    public static function getByRelated(string $type, int $id): array
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT d.*, u.full_name as uploader_name 
                              FROM documents d 
                              LEFT JOIN users u ON d.uploaded_by = u.user_id
                              WHERE d.related_type = :type AND d.related_id = :id 
                              ORDER BY d.uploaded_at DESC");
        $stmt->execute(['type' => $type, 'id' => $id]);
        return $stmt->fetchAll();
    }

    /**
     * Upload and save a file
     */
    public static function upload(array $file, string $relatedType, int $relatedId, string $category, string $description = ''): ?int
    {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK)
            return null;
        if ($file['size'] > MAX_FILE_SIZE)
            return null;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allAllowed = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
        if (!in_array($ext, $allAllowed))
            return null;

        // Determine upload subdirectory
        $subDir = match ($category) {
            'photo', 'boundary_image' => 'photos',
            'map' => 'maps',
            default => 'documents',
        };

        $uploadDir = UPLOAD_PATH . $subDir . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);

        // Generate unique filename
        $newName = $relatedType . '_' . $relatedId . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
        $filePath = $uploadDir . $newName;
        $webPath = 'uploads/' . $subDir . '/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $filePath))
            return null;

        // Save to database
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO documents 
            (related_type, related_id, file_name, file_path, file_type, file_size, doc_category, description, uploaded_by)
            VALUES (:related_type, :related_id, :file_name, :file_path, :file_type, :file_size, :doc_category, :description, :uploaded_by)");

        $stmt->execute([
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'file_name' => $file['name'],
            'file_path' => $webPath,
            'file_type' => $ext,
            'file_size' => $file['size'],
            'doc_category' => $category,
            'description' => $description,
            'uploaded_by' => $_SESSION['user_id'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Delete document and file
     */
    public static function delete(int $docId): bool
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT file_path FROM documents WHERE doc_id = :id");
        $stmt->execute(['id' => $docId]);
        $doc = $stmt->fetch();

        if ($doc) {
            $fullPath = BASE_PATH . $doc['file_path'];
            if (file_exists($fullPath))
                unlink($fullPath);

            $del = $db->prepare("DELETE FROM documents WHERE doc_id = :id");
            return $del->execute(['id' => $docId]);
        }
        return false;
    }
}
