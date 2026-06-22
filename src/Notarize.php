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

    public function __construct()
    {
        $this->db             = Database::getInstance();
        $this->privateKeyPath = $_ENV['PRIVATE_KEY_PATH'] ?? '/etc/notarize/keys/private.pem';
        $this->publicKeyPath  = $_ENV['PUBLIC_KEY_PATH']  ?? '/etc/notarize/keys/public.pem';
        $this->uploadDir      = UPLOAD_DIR;
    }

    public function notarize(int $userId, array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => $this->uploadErrorMessage($file['error'])];
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            return ['error' => 'Unsupported file type. Allowed: PDF, images (JPG/PNG/GIF/WebP), Word documents, plain text.'];
        }

        if ($file['size'] > MAX_UPLOAD_BYTES) {
            return ['error' => 'File exceeds the ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB size limit.'];
        }

        $fileHash = hash_file('sha256', $file['tmp_name']);

        $privateKeyPem = @file_get_contents($this->privateKeyPath);
        if (!$privateKeyPem) {
            return ['error' => 'Signing key unavailable. Please contact support.'];
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            return ['error' => 'Invalid signing key. Please contact support.'];
        }

        if (!openssl_sign($fileHash, $rawSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return ['error' => 'Failed to sign document.'];
        }

        $signature = base64_encode($rawSignature);
        $uuid      = $this->generateUuid();

        $userDir = $this->uploadDir . '/' . $userId;
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $storedName = $uuid . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $userDir . '/' . $storedName);

        $stmt = $this->db->prepare(
            'INSERT INTO documents
             (user_id, original_filename, stored_filename, mime_type, file_size, file_hash, signature, certificate_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $file['name'],
            $storedName,
            $mimeType,
            $file['size'],
            $fileHash,
            $signature,
            $uuid,
        ]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId(), 'uuid' => $uuid];
    }

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

    public function getDocumentByUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, u.name AS user_name, u.email AS user_email
             FROM documents d
             JOIN users u ON d.user_id = u.id
             WHERE d.certificate_uuid = ?'
        );
        $stmt->execute([$uuid]);
        $doc = $stmt->fetch();
        if (!$doc) {
            return null;
        }

        $publicKeyPem = @file_get_contents($this->publicKeyPath);
        if ($publicKeyPem) {
            $result               = openssl_verify($doc['file_hash'], base64_decode($doc['signature']), $publicKeyPem, OPENSSL_ALGO_SHA256);
            $doc['sig_valid']     = ($result === 1);
        } else {
            $doc['sig_valid'] = null;
        }

        return $doc;
    }

    public function getUserDocuments(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM documents WHERE user_id = ? ORDER BY notarized_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function generateQrSvg(string $url): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel'   => QRCode::ECC_H,
            'scale'      => 5,
            'imageBase64' => false,
        ]);
        return (new QRCode($options))->render($url);
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
