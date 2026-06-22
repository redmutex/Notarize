<?php
declare(strict_types=1);

namespace App;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use setasign\Fpdi\Tcpdf\Fpdi;

class NotarizePDF
{
    private const NAVY    = [30,  58,  95];
    private const GOLD    = [201, 168, 76];
    private const WHITE   = [255, 255, 255];
    private const LGREY   = [248, 249, 250];
    private const FOOT_H  = 11.0; // footer height mm

    // ── Public entry point ───────────────────────────────────────────

    public function generate(array $doc, string $verifyUrl, string $uploadDir): ?string
    {
        try {
            $qrSvgPath = $this->saveQrSvg($verifyUrl);
            $srcFile   = $uploadDir . '/' . $doc['user_id'] . '/' . $doc['stored_filename'];
            $isPdf     = $doc['mime_type'] === 'application/pdf';
            $isImage   = str_starts_with($doc['mime_type'], 'image/');

            $pdf = $this->makePdf();

            // Original document pages (with footer on each)
            if ($isPdf && is_file($srcFile)) {
                $this->appendPdfPages($pdf, $doc, $srcFile);
            } elseif ($isImage && is_file($srcFile)) {
                $this->appendImagePage($pdf, $doc, $srcFile);
            }

            // Certificate is always the last page, also gets footer
            $pdf->AddPage('P', 'A4');
            $this->drawCertificate($pdf, $doc, $verifyUrl, $qrSvgPath);
            $this->drawPageFooter($pdf, $doc, 210.0, 297.0);

            $outPath = $uploadDir . '/' . $doc['user_id'] . '/' . $doc['certificate_uuid'] . '_notarized.pdf';
            $pdf->Output($outPath, 'F');

            if ($qrSvgPath) @unlink($qrSvgPath);
            return $outPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── PDF building helpers ─────────────────────────────────────────

    private function makePdf(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Notarize — notarize.onrite.cloud');
        $pdf->SetAuthor('notarize.onrite.cloud');
        $pdf->SetTitle('Notarized Document');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        return $pdf;
    }

    private function saveQrSvg(string $url): ?string
    {
        try {
            $opts = new QROptions([
                'outputType'  => QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel'    => QRCode::ECC_H,
                'scale'       => 6,
                'imageBase64' => false,
            ]);
            $svg  = (new QRCode($opts))->render($url);
            $path = sys_get_temp_dir() . '/qrnotarize_' . uniqid() . '.svg';
            file_put_contents($path, $svg);
            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Original document pages ──────────────────────────────────────

    /** Import pages from an existing PDF, overlay footer on each. */
    private function appendPdfPages(Fpdi $pdf, array $doc, string $srcFile): void
    {
        try {
            $pageCount = $pdf->setSourceFile($srcFile);
        } catch (\Throwable $e) {
            // PDF not parseable (encrypted / PDF 1.7+) — show placeholder
            $pdf->AddPage('P', 'A4');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY(20, 90);
            $pdf->MultiCell(170, 7,
                'Original file: ' . $doc['original_filename'] .
                "\n\nThis document's content could not be embedded because the PDF version " .
                "or encryption is not supported. The certificate on the final page confirms " .
                "its notarization. Verify integrity by computing the SHA-256 hash of the " .
                "original file and comparing it with the hash in the certificate.",
                0, 'C');
            $this->drawPageFooter($pdf, $doc, 210.0, 297.0);
            return;
        }

        for ($i = 1; $i <= $pageCount; $i++) {
            try {
                $tplId = $pdf->importPage($i);
                $size  = $pdf->getTemplateSize($tplId);
                $w     = (float)$size['width'];
                $h     = (float)$size['height'];
                $ori   = ($w > $h) ? 'L' : 'P';

                $pdf->AddPage($ori, [$w, $h]);
                // Fill page with original content, footer overlays the bottom edge
                $pdf->useTemplate($tplId, 0, 0, $w, $h, true);
                $this->drawPageFooter($pdf, $doc, $w, $h);
            } catch (\Throwable $e) {
                // Skip unreadable page silently
            }
        }
    }

    /** Embed an image (proportionally fitted), footer at the bottom. */
    private function appendImagePage(Fpdi $pdf, array $doc, string $srcFile): void
    {
        $pageW = 210.0;
        $pageH = 297.0;
        $maxH  = $pageH - self::FOOT_H - 2.0; // 2 mm breathing gap

        $pdf->AddPage('P', 'A4');

        try {
            $size = @getimagesize($srcFile);
            if ($size && $size[0] > 0 && $size[1] > 0) {
                $ratio = $size[0] / $size[1];
                if ($ratio > $pageW / $maxH) {
                    $w = $pageW; $h = $pageW / $ratio;
                } else {
                    $h = $maxH;  $w = $maxH * $ratio;
                }
                $x = ($pageW - $w) / 2.0;
                $pdf->Image($srcFile, $x, 0.0, $w, $h);
            } else {
                $pdf->Image($srcFile, 0, 0, $pageW, $maxH, '', '', '', false, 96, '', false, false, 0, true);
            }
        } catch (\Throwable $e) {
            // Image embed failed — footer still gets added below
        }

        $this->drawPageFooter($pdf, $doc, $pageW, $pageH);
    }

    // ── Footer strip (every page) ────────────────────────────────────

    private function drawPageFooter(Fpdi $pdf, array $doc, float $pageW, float $pageH): void
    {
        $fH = self::FOOT_H;
        $fY = $pageH - $fH;

        [$nR, $nG, $nB] = self::NAVY;
        [$gR, $gG, $gB] = self::GOLD;
        [$wR, $wG, $wB] = self::WHITE;

        // Gold top separator
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(0, $fY, $pageW, $fY);

        // Navy background
        $pdf->SetFillColor($nR, $nG, $nB);
        $pdf->Rect(0, $fY + 0.4, $pageW, $fH - 0.4, 'F');

        // Logo zone: rightmost 44mm, separated by a thin vertical gold line
        $logoZone = 44.0;
        $textW    = $pageW - $logoZone - 4.0; // 3mm left margin + 1mm gap

        // Line 1 — certificate metadata (gold)
        $date  = date('Y-m-d H:i', strtotime($doc['notarized_at'])) . ' UTC';
        $line1 = 'NOTARIZED  |  notarize.onrite.cloud  |  Cert: ' . $doc['certificate_uuid'] . '  |  ' . $date;

        $pdf->SetFont('helvetica', 'B', 5.2);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY(3.0, $fY + 1.5);
        $pdf->Cell($textW, 3.5, $line1, 0, 0, 'L');

        // Line 2 — truncated RSA-4096 signature (white monospace)
        $line2 = 'RSA-4096/SHA-256 Sig:  ' . substr($doc['signature'], 0, 100) . '...';

        $pdf->SetFont('courier', '', 4.8);
        $pdf->SetTextColor($wR, $wG, $wB);
        $pdf->SetXY(3.0, $fY + 6.0);
        $pdf->Cell($textW, 3.5, $line2, 0, 0, 'L');

        // Vertical gold divider
        $divX = $pageW - $logoZone - 0.5;
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.35);
        $pdf->Line($divX, $fY + 1.5, $divX, $fY + $fH - 1.5);

        // Logo: brand name (gold, bold)
        $lX = $pageW - $logoZone + 2.0;
        $lW = $logoZone - 5.0; // 3mm right margin
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY($lX, $fY + 1.8);
        $pdf->Cell($lW, 4.5, 'NOTARIZE', 0, 0, 'R');

        // Logo: domain (white, small)
        $pdf->SetFont('helvetica', '', 4.2);
        $pdf->SetTextColor($wR, $wG, $wB);
        $pdf->SetXY($lX, $fY + 6.5);
        $pdf->Cell($lW, 3.0, 'notarize.onrite.cloud', 0, 0, 'R');
    }

    // ── Certificate page ─────────────────────────────────────────────

    private function drawCertificate(Fpdi $pdf, array $doc, string $verifyUrl, ?string $qrPath): void
    {
        [$nR, $nG, $nB] = self::NAVY;
        [$gR, $gG, $gB] = self::GOLD;
        [$wR, $wG, $wB] = self::WHITE;

        // Certificate occupies y=5 to y=282, footer strip from y=286 to y=297

        // Borders
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(2.0);
        $pdf->Rect(5, 5, 200, 277, 'D');  // outer
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(8, 8, 194, 271, 'D');  // inner

        // Header band (navy)
        $pdf->SetFillColor($nR, $nG, $nB);
        $pdf->Rect(8, 8, 194, 43, 'F');

        // Seal
        $pdf->SetFillColor($gR, $gG, $gB);
        $pdf->SetDrawColor($wR, $wG, $wB);
        $pdf->SetLineWidth(0.8);
        $pdf->Circle(28, 29, 11, 0, 360, 'DF');
        $pdf->SetFillColor($nR, $nG, $nB);
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.5);
        $pdf->Circle(28, 29, 8, 0, 360, 'DF');
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY(18, 22);
        $pdf->Cell(20, 14, "\u{2713}", 0, 0, 'C');

        // Title
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor($wR, $wG, $wB);
        $pdf->SetXY(46, 13);
        $pdf->Cell(158, 9, 'CERTIFICATE OF NOTARIZATION', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY(46, 23);
        $pdf->Cell(158, 6, 'NOTARIZE  |  notarize.onrite.cloud', 0, 0, 'C');
        $pdf->SetFont('helvetica', 'I', 7.5);
        $pdf->SetTextColor($wR, $wG, $wB);
        $pdf->SetXY(46, 30);
        $pdf->MultiCell(158, 4,
            "This certifies that the document described below was received and digitally notarized on the date shown. " .
            "The cryptographic signature confirms the document's content at the time of notarization.",
            0, 'C', false, 0);

        // Gold divider
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(10, 54, 200, 54);

        // Data table (left 130mm column)
        $rows = [
            ['Certificate ID',  $doc['certificate_uuid'], true],
            ['Document Name',   $this->clip($doc['original_filename'], 50), false],
            ['File Type',       $doc['mime_type'], false],
            ['File Size',       $this->fmtBytes((int)$doc['file_size']), false],
            ['SHA-256 Hash',    $doc['file_hash'], true],
            ['Notarized By',    $doc['user_name'] ?? '', false],
            ['Date & Time',     date('F j, Y  H:i:s', strtotime($doc['notarized_at'])) . ' UTC', false],
            ['Algorithm',       'RSA-4096 / SHA-256', false],
        ];

        $tX = 10; $tY = 57; $tW = 130; $lW = 38; $rH = 9;

        foreach ($rows as $i => [$label, $value, $mono]) {
            $y = $tY + $i * $rH;
            if ($i % 2 === 0) {
                $pdf->SetFillColor(...self::LGREY);
                $pdf->Rect($tX, $y, $tW, $rH, 'F');
            }
            $pdf->SetDrawColor(218, 218, 218);
            $pdf->SetLineWidth(0.2);
            $pdf->Rect($tX, $y, $tW, $rH, 'D');

            $pdf->SetTextColor($nR, $nG, $nB);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetXY($tX + 2, $y + 1.5);
            $pdf->Cell($lW - 2, $rH - 3, $label, 0, 0, 'L');

            $pdf->SetTextColor(80, 80, 80);
            if ($mono) {
                $pdf->SetFont('courier', '', 6.0);
                $pdf->SetXY($tX + $lW + 1, $y + 2.0);
                $pdf->Cell($tW - $lW - 3, $rH - 4, $this->clip($value, 56), 0, 0, 'L');
            } else {
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetXY($tX + $lW + 1, $y + 1.5);
                $pdf->Cell($tW - $lW - 3, $rH - 3, $this->clip($value, 44), 0, 0, 'L');
            }
        }

        // QR code (right column)
        $qX = 148; $qY = 57; $qSz = 55;
        $pdf->SetTextColor($nR, $nG, $nB);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($qX, $qY);
        $pdf->Cell($qSz, 7, 'Scan to Verify', 0, 0, 'C');
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.8);
        $pdf->Rect($qX, $qY + 7, $qSz, $qSz, 'D');
        if ($qrPath && is_file($qrPath)) {
            try {
                $pdf->imageSVG($qrPath, $qX + 2, $qY + 9, $qSz - 4, $qSz - 4);
            } catch (\Throwable $e) { /* SVG embed failed */ }
        }
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('courier', '', 5);
        $pdf->SetXY($qX, $qY + $qSz + 9);
        $pdf->MultiCell($qSz, 3.2, wordwrap($verifyUrl, 32, "\n", true), 0, 'C', false, 0);

        // Full digital signature box
        $sigY = $tY + count($rows) * $rH + 6;  // ≈ y=135
        $pdf->SetFillColor(246, 246, 244);
        $pdf->SetDrawColor(218, 210, 180);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect($tX, $sigY, $tW, 36, 'DF');

        $pdf->SetTextColor($nR, $nG, $nB);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetXY($tX + 3, $sigY + 2.5);
        $pdf->Cell(60, 5, 'Full Digital Signature (RSA-4096 / SHA-256):', 0, 0, 'L');

        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetFont('courier', '', 5.0);
        $pdf->SetXY($tX + 3, $sigY + 9);
        $pdf->MultiCell($tW - 6, 3.6, wordwrap($doc['signature'], 80, "\n", true), 0, 'L', false, 0);

        // Verification instructions
        $instrY = $sigY + 36 + 5;  // ≈ y=176
        $pdf->SetFillColor(240, 245, 252);
        $pdf->SetDrawColor(180, 200, 225);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($tX, $instrY, 185, 40, 'DF');

        $pdf->SetTextColor($nR, $nG, $nB);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetXY($tX + 4, $instrY + 3);
        $pdf->Cell(177, 5, 'Independent Verification', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY($tX + 4, $instrY + 10);
        $pdf->MultiCell(177, 4.5,
            "1. Obtain the original file and compute its SHA-256 hash  (e.g.  sha256sum <file>  on Linux/macOS).\n" .
            "2. Compare the computed hash to the SHA-256 Hash shown in the table above.\n" .
            "3. If they match, the file is identical to the one that was notarized on the date shown.\n" .
            "4. Visit the URL below or scan the QR code to verify online against the notarization record.",
            0, 'L', false, 0);

        // Issued-by line
        $issueY = $instrY + 40 + 5;  // ≈ y=221
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $issueY, 200, $issueY);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(10, $issueY + 2.5);
        $pdf->Cell(95, 5, 'Issued by Notarize — notarize.onrite.cloud', 0, 0, 'L');
        $pdf->SetXY(105, $issueY + 2.5);
        $pdf->Cell(90, 5, $verifyUrl, 0, 0, 'R');
    }

    // ── Utilities ────────────────────────────────────────────────────

    private function fmtBytes(int $b): string
    {
        if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
        if ($b >= 1024)    return round($b / 1024, 1) . ' KB';
        return $b . ' B';
    }

    private function clip(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '...' : $s;
    }
}
