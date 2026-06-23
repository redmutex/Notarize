<?php
declare(strict_types=1);

namespace App;

use PDO;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Notarize
{
    private PDO    $db;
    private string $privateKeyPath;
    private string $publicKeyPath;
    private string $uploadDir;

    private const ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    private const ALLOWED_ID_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct()
    {
        $this->db             = Database::getInstance();
        $this->privateKeyPath = $_ENV['PRIVATE_KEY_PATH'] ?? '/etc/notarize/keys/private.pem';
        $this->publicKeyPath  = $_ENV['PUBLIC_KEY_PATH']  ?? '/etc/notarize/keys/public.pem';
        $this->uploadDir      = UPLOAD_DIR;
    }

    // ── Submission (pending → admin review) ──────────────────────────

    public function submit(int $userId, array $docFile, array $photoIdFile, array $selfieFile): array
    {
        // Validate document
        if ($docFile['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Document: ' . $this->uploadErrorMessage($docFile['error'])];
        }
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($docFile['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            return ['error' => 'Unsupported document type. Allowed: PDF, images, Word, plain text.'];
        }
        if ($docFile['size'] > MAX_UPLOAD_BYTES) {
            return ['error' => 'Document exceeds the ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB size limit.'];
        }

        // Validate photo ID
        if ($photoIdFile['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Photo ID: ' . $this->uploadErrorMessage($photoIdFile['error'])];
        }
        $photoMime = $finfo->file($photoIdFile['tmp_name']);
        if (!in_array($photoMime, self::ALLOWED_ID_MIME, true)) {
            return ['error' => 'Photo ID must be a JPEG, PNG, or WebP image.'];
        }
        if ($photoIdFile['size'] > 10 * 1024 * 1024) {
            return ['error' => 'Photo ID image is too large (max 10 MB).'];
        }

        // Validate selfie
        if ($selfieFile['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Selfie: ' . $this->uploadErrorMessage($selfieFile['error'])];
        }
        $selfieMime = $finfo->file($selfieFile['tmp_name']);
        if (!in_array($selfieMime, self::ALLOWED_ID_MIME, true)) {
            return ['error' => 'Selfie must be a JPEG, PNG, or WebP image.'];
        }
        if ($selfieFile['size'] > 10 * 1024 * 1024) {
            return ['error' => 'Selfie image is too large (max 10 MB).'];
        }

        // Prepare storage directory
        $userDir = $this->uploadDir . '/' . $userId;
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        // Generate a temporary UUID for file naming (will be replaced on approval)
        $tempId = bin2hex(random_bytes(16));

        // Store document
        $ext         = strtolower(pathinfo($docFile['name'], PATHINFO_EXTENSION));
        $storedName  = $tempId . '.' . $ext;
        move_uploaded_file($docFile['tmp_name'], $userDir . '/' . $storedName);

        // Store photo ID
        $photoExt      = strtolower(pathinfo($photoIdFile['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $photoFilename = $tempId . '_photo_id.' . $photoExt;
        move_uploaded_file($photoIdFile['tmp_name'], $userDir . '/' . $photoFilename);

        // Store selfie
        $selfieExt      = strtolower(pathinfo($selfieFile['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $selfieFilename = $tempId . '_selfie.' . $selfieExt;
        move_uploaded_file($selfieFile['tmp_name'], $userDir . '/' . $selfieFilename);

        // Create pending DB record
        $stmt = $this->db->prepare(
            "INSERT INTO documents
             (user_id, status, original_filename, stored_filename, photo_id_filename, selfie_filename,
              mime_type, file_size, file_hash, signature, certificate_uuid, notarized_at)
             VALUES (?, 'pending', ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)"
        );
        $stmt->execute([
            $userId,
            $docFile['name'],
            $storedName,
            $photoFilename,
            $selfieFilename,
            $mimeType,
            $docFile['size'],
        ]);
        $docId = (int)$this->db->lastInsertId();

        // Notify admin
        $doc  = $this->getDocumentForAdmin($docId);
        $user = $this->getUserById($userId);
        if ($doc && $user) {
            (new Mailer())->sendAdminReviewRequest($doc, $user);
        }

        return ['success' => true, 'id' => $docId];
    }

    // ── Admin approval: sign + issue certificate ──────────────────────

    public function approve(int $docId, int $adminId): array
    {
        $doc = $this->getDocumentForAdmin($docId);
        if (!$doc || $doc['status'] !== 'pending') {
            return ['error' => 'Document not found or not in pending state.'];
        }

        $filePath = $this->uploadDir . '/' . (int)$doc['user_id'] . '/' . $doc['stored_filename'];
        if (!is_file($filePath)) {
            return ['error' => 'Stored document file not found.'];
        }

        // Cryptographic signing
        $fileHash     = hash_file('sha256', $filePath);
        $privateKeyPem = @file_get_contents($this->privateKeyPath);
        if (!$privateKeyPem) {
            return ['error' => 'Signing key unavailable.'];
        }
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            return ['error' => 'Invalid signing key.'];
        }
        if (!openssl_sign($fileHash, $rawSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return ['error' => 'Failed to sign document.'];
        }
        $signature = base64_encode($rawSignature);
        $uuid      = $this->generateUuid();
        $now       = date('Y-m-d H:i:s');

        // Build the synthetic doc array needed by NotarizePDF before touching the DB.
        // generate() needs notarized_at, certificate_uuid, signature, file_hash, user_name.
        $user = $this->getUserById((int)$doc['user_id']);
        $docForPdf = array_merge($doc, [
            'file_hash'       => $fileHash,
            'signature'       => $signature,
            'certificate_uuid'=> $uuid,
            'notarized_at'    => $now,
            'user_name'       => $user['name'] ?? '',
        ]);

        // Generate PDF first — if it fails the DB stays untouched and the doc remains pending.
        $verifyUrl  = APP_URL . '/verify.php?uuid=' . $uuid;
        $pdfOutPath = $this->uploadDir . '/' . (int)$doc['user_id'] . '/' . $uuid . '_notarized.pdf';
        try {
            $generated = (new NotarizePDF())->generate($docForPdf, $verifyUrl, $this->uploadDir);
        } catch (\Throwable $e) {
            $generated = null;
            error_log('[Notarize] PDF generation exception for doc #' . $docId . ': ' . $e->getMessage());
        }

        if (!$generated || !is_file($pdfOutPath)) {
            return ['error' => 'PDF generation failed. Document has not been approved.'];
        }

        // PDF confirmed on disk — now commit the approval to the DB.
        $this->db->prepare(
            "UPDATE documents
             SET status = 'approved', file_hash = ?, signature = ?, certificate_uuid = ?,
                 notarized_at = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?"
        )->execute([$fileHash, $signature, $uuid, $now, $adminId, $docId]);

        // Email user
        if ($user) {
            $docForPdf['id'] = $docId; // ensure id is present for email links
            (new Mailer())->sendApprovalConfirmation($docForPdf, $user);
        }

        return ['success' => true, 'uuid' => $uuid];
    }

    public function reject(int $docId, int $adminId, string $notes): bool
    {
        $doc = $this->getDocumentForAdmin($docId);
        if (!$doc || $doc['status'] !== 'pending') {
            return false;
        }

        $this->db->prepare(
            "UPDATE documents
             SET status = 'rejected', review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?"
        )->execute([trim($notes), $adminId, $docId]);

        $user = $this->getUserById((int)$doc['user_id']);
        if ($user) {
            (new Mailer())->sendRejectionNotice($doc, $user, trim($notes));
        }

        return true;
    }

    // ── Document retrieval ────────────────────────────────────────────

    public function getDocument(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, u.name AS user_name, u.email AS user_email
             FROM documents d
             JOIN users u ON d.user_id = u.id
             WHERE d.id = ? AND d.user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function getDocumentForAdmin(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, u.name AS user_name, u.email AS user_email
             FROM documents d
             JOIN users u ON d.user_id = u.id
             WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getDocumentByUuid(string $uuid): ?array
    {
        // Only return approved (notarized) documents on public verify page
        $stmt = $this->db->prepare(
            "SELECT d.*, u.name AS user_name, u.email AS user_email
             FROM documents d
             JOIN users u ON d.user_id = u.id
             WHERE d.certificate_uuid = ? AND d.status = 'approved'"
        );
        $stmt->execute([$uuid]);
        $doc = $stmt->fetch();
        if (!$doc) {
            return null;
        }

        $publicKeyPem = @file_get_contents($this->publicKeyPath);
        if ($publicKeyPem && $doc['file_hash'] && $doc['signature']) {
            $result           = openssl_verify($doc['file_hash'], base64_decode($doc['signature']), $publicKeyPem, OPENSSL_ALGO_SHA256);
            $doc['sig_valid'] = ($result === 1);
        } else {
            $doc['sig_valid'] = null;
        }

        return $doc;
    }

    public function getUserDocuments(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM documents WHERE user_id = ? ORDER BY submitted_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getPendingDocuments(): array
    {
        $stmt = $this->db->query(
            "SELECT d.*, u.name AS user_name, u.email AS user_email
             FROM documents d
             JOIN users u ON d.user_id = u.id
             WHERE d.status = 'pending'
             ORDER BY d.submitted_at ASC"
        );
        return $stmt->fetchAll();
    }

    public function getPendingCount(): int
    {
        return (int)$this->db->query(
            "SELECT COUNT(*) FROM documents WHERE status = 'pending'"
        )->fetchColumn();
    }

    // ── File management ───────────────────────────────────────────────

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, stored_filename, photo_id_filename, selfie_filename FROM documents WHERE id = ?'
        );
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc || (int)$doc['user_id'] !== $userId) {
            return false;
        }

        $base = $this->uploadDir . '/' . $userId . '/';
        foreach (['stored_filename', 'photo_id_filename', 'selfie_filename'] as $col) {
            if ($doc[$col] && is_file($base . $doc[$col])) {
                @unlink($base . $doc[$col]);
            }
        }
        // Remove notarized PDF if present
        if ($doc['stored_filename']) {
            $stem = pathinfo($doc['stored_filename'], PATHINFO_FILENAME);
            $pdf  = $base . $stem . '_notarized.pdf';
            if (is_file($pdf)) @unlink($pdf);
        }

        $this->db->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
        return true;
    }

    // ── QR code ──────────────────────────────────────────────────────

    public function generateQrHtml(string $url): string
    {
        try {
            $options = new QROptions([
                'outputType'  => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'    => QRCode::ECC_H,
                'scale'       => 6,
                'imageBase64' => true,
            ]);
            $src = (new QRCode($options))->render($url);
            return '<img src="' . $src . '" width="160" height="160" alt="QR Code" style="display:block">';
        } catch (\Throwable $e) {
            return '<div style="width:160px;height:160px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:.75rem;color:#888">QR unavailable</div>';
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function getUserById(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            default               => 'Unknown upload error.',
        };
    }
}
