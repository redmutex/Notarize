<?php
declare(strict_types=1);

namespace App;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use TCPDF;

class NotarizePDF
{
    private const NAVY = [30,  58,  95];
    private const GOLD = [201, 168, 76];
    private const WHITE = [255, 255, 255];
    private const LGREY = [248, 249, 250];
    private const DGREY = [80,  80,  80];

    public function generate(array $doc, string $verifyUrl, string $uploadDir): ?string
    {
        try {
            $qrSvgPath = $this->saveQrSvg($verifyUrl);
            $pdf       = $this->makePdf();

            $pdf->AddPage('P', 'A4');
            $this->drawCertificate($pdf, $doc, $verifyUrl, $qrSvgPath);

            $srcFile = $uploadDir . '/' . $doc['user_id'] . '/' . $doc['stored_filename'];
            if (is_file($srcFile) && str_starts_with($doc['mime_type'], 'image/')) {
                $pdf->AddPage('P', 'A4');
                $this->drawImagePage($pdf, $doc, $srcFile);
            }

            $outPath = $uploadDir . '/' . $doc['user_id'] . '/' . $doc['certificate_uuid'] . '_notarized.pdf';
            $pdf->Output($outPath, 'F');

            if ($qrSvgPath) @unlink($qrSvgPath);
            return $outPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function makePdf(): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Notarize — notarize.onrite.cloud');
        $pdf->SetAuthor('notarize.onrite.cloud');
        $pdf->SetTitle('Certificate of Notarization');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        return $pdf;
    }

    private function saveQrSvg(string $url): ?string
    {
        try {
            $options = new QROptions([
                'outputType'  => QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel'    => QRCode::ECC_H,
                'scale'       => 6,
                'imageBase64' => false,
            ]);
            $svg  = (new QRCode($options))->render($url);
            $path = sys_get_temp_dir() . '/qrnotarize_' . uniqid() . '.svg';
            file_put_contents($path, $svg);
            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function drawCertificate(TCPDF $pdf, array $doc, string $verifyUrl, ?string $qrPath): void
    {
        [$nR, $nG, $nB] = self::NAVY;
        [$gR, $gG, $gB] = self::GOLD;
        [$wR, $wG, $wB] = self::WHITE;

        // Outer & inner gold borders
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(2.0);
        $pdf->Rect(5, 5, 200, 287, 'D');
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(8, 8, 194, 281, 'D');

        // Header band (navy)
        $pdf->SetFillColor($nR, $nG, $nB);
        $pdf->SetDrawColor($nR, $nG, $nB);
        $pdf->Rect(8, 8, 194, 43, 'F');

        // Seal: gold outer circle, navy inner
        $pdf->SetFillColor($gR, $gG, $gB);
        $pdf->SetDrawColor($wR, $wG, $wB);
        $pdf->SetLineWidth(0.8);
        $pdf->Circle(28, 29, 11, 0, 360, 'DF');
        $pdf->SetFillColor($nR, $nG, $nB);
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.5);
        $pdf->Circle(28, 29, 8, 0, 360, 'DF');

        // Checkmark in seal
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY(18, 22);
        $pdf->Cell(20, 14, "\u{2713}", 0, 0, 'C');

        // Certificate title
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor($wR, $wG, $wB);
        $pdf->SetXY(46, 13);
        $pdf->Cell(158, 9, 'CERTIFICATE OF NOTARIZATION', 0, 0, 'C');

        // Subtitle
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY(46, 23);
        $pdf->Cell(158, 6, 'NOTARIZE  \u{2022}  notarize.onrite.cloud', 0, 0, 'C');

        // Intro text
        $pdf->SetFont('helvetica', 'I', 7.5);
        $pdf->SetTextColor($wR, $wG, $wB);
        $pdf->SetXY(46, 30);
        $pdf->MultiCell(158, 4, 'This certifies that the document described below was received and digitally notarized on the date shown. The cryptographic signature confirms the document\'s content at the time of notarization.', 0, 'C', false, 0);

        // Gold separator line
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(10, 54, 200, 54);

        // ── Data table ──────────────────────────────────────────────
        $rows = [
            ['Certificate ID',  $doc['certificate_uuid'], true],
            ['Document Name',   $this->clip($doc['original_filename'], 52), false],
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

            $pdf->SetTextColor(...self::DGREY);
            if ($mono) {
                $pdf->SetFont('courier', '', 6);
                $pdf->SetXY($tX + $lW + 1, $y + 2);
                $pdf->Cell($tW - $lW - 3, $rH - 4, $this->clip($value, 56), 0, 0, 'L');
            } else {
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetXY($tX + $lW + 1, $y + 1.5);
                $pdf->Cell($tW - $lW - 3, $rH - 3, $this->clip($value, 44), 0, 0, 'L');
            }
        }

        // ── Signature box ────────────────────────────────────────────
        $sigY = $tY + count($rows) * $rH + 5;
        $pdf->SetFillColor(246, 246, 244);
        $pdf->SetDrawColor(218, 210, 180);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect($tX, $sigY, $tW, 32, 'DF');

        $pdf->SetTextColor($nR, $nG, $nB);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetXY($tX + 3, $sigY + 2.5);
        $pdf->Cell(60, 5, 'Digital Signature (RSA-4096):', 0, 0, 'L');

        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetFont('courier', '', 5.2);
        $pdf->SetXY($tX + 3, $sigY + 9);
        $wrapped = wordwrap($doc['signature'], 78, "\n", true);
        $pdf->MultiCell($tW - 6, 3.8, $wrapped, 0, 'L', false, 0);

        // ── QR code ──────────────────────────────────────────────────
        $qX = 148; $qY = 57; $qSz = 52;
        $pdf->SetTextColor($nR, $nG, $nB);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($qX, $qY);
        $pdf->Cell($qSz, 7, 'Scan to Verify', 0, 0, 'C');

        // Gold border around QR
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.8);
        $pdf->Rect($qX, $qY + 7, $qSz, $qSz, 'D');

        if ($qrPath && is_file($qrPath)) {
            try {
                $pdf->imageSVG($qrPath, $qX + 2, $qY + 9, $qSz - 4, $qSz - 4);
            } catch (\Throwable $e) {
                // SVG embed failed — leave blank
            }
        }

        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('courier', '', 5);
        $pdf->SetXY($qX, $qY + $qSz + 9);
        $pdf->MultiCell($qSz, 3.2, wordwrap($verifyUrl, 32, "\n", true), 0, 'C', false, 0);

        // ── Footer band ──────────────────────────────────────────────
        $pdf->SetFillColor($nR, $nG, $nB);
        $pdf->SetDrawColor($nR, $nG, $nB);
        $pdf->Rect(8, 268, 194, 21, 'F');

        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY(12, 273);
        $pdf->Cell(95, 5, 'Issued by Notarize \u{2014} notarize.onrite.cloud', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor($wR, $wG, $wB);
        $pdf->SetXY(107, 273);
        $pdf->Cell(90, 5, 'Certificate ID: ' . $doc['certificate_uuid'], 0, 0, 'R');

        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY(12, 280);
        $pdf->Cell(186, 5, 'Verify: ' . $verifyUrl, 0, 0, 'L');
    }

    private function drawImagePage(TCPDF $pdf, array $doc, string $filePath): void
    {
        [$nR, $nG, $nB] = self::NAVY;
        [$gR, $gG, $gB] = self::GOLD;

        // Embed image — compute aspect-fitted dimensions
        try {
            $size = getimagesize($filePath);
            if ($size) {
                [$imgW, $imgH] = $size;
                $ratio  = $imgW / $imgH;
                $maxW   = 190;
                $maxH   = 240;
                if ($ratio > $maxW / $maxH) {
                    $w = $maxW; $h = $maxW / $ratio;
                } else {
                    $h = $maxH; $w = $maxH * $ratio;
                }
                $x = (210 - $w) / 2;
                $pdf->Image($filePath, $x, 10, $w, $h);
            } else {
                $pdf->Image($filePath, 10, 10, 190, 240, '', '', '', false, 96, 'C', false, false, 0, true);
            }
        } catch (\Throwable $e) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY(10, 120);
            $pdf->Cell(190, 10, '[Image could not be embedded]', 0, 0, 'C');
        }

        // Diagonal watermark "NOTARIZED"
        $pdf->StartTransform();
        $pdf->Rotate(46, 105, 140);
        $pdf->SetFont('helvetica', 'B', 68);
        $pdf->SetTextColor($nR, $nG, $nB);
        $pdf->setAlpha(0.10);
        $pdf->SetXY(22, 128);
        $pdf->Cell(166, 26, 'NOTARIZED', 0, 0, 'C');
        $pdf->setAlpha(1.0);
        $pdf->StopTransform();

        // Stamp box — bottom-right corner
        $sx = 128; $sy = 250; $sw = 72; $sh = 40;

        $pdf->SetFillColor($nR, $nG, $nB);
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(1.5);
        $pdf->RoundedRect($sx, $sy, $sw, $sh, 2.5, '1111', 'DF');

        // Inner gold outline
        $pdf->SetLineWidth(0.4);
        $pdf->RoundedRect($sx + 2.5, $sy + 2.5, $sw - 5, $sh - 5, 1.5, '1111', 'D');

        // "NOTARIZED" heading
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY($sx, $sy + 3.5);
        $pdf->Cell($sw, 7, 'NOTARIZED', 0, 0, 'C');

        // Gold divider
        $pdf->SetDrawColor($gR, $gG, $gB);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($sx + 4, $sy + 12, $sx + $sw - 4, $sy + 12);

        // Details
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(220, 220, 220);

        $pdf->SetXY($sx, $sy + 14);
        $pdf->Cell($sw, 4.5, date('F j, Y', strtotime($doc['notarized_at'])), 0, 0, 'C');

        $pdf->SetXY($sx, $sy + 19);
        $pdf->Cell($sw, 4.5, 'Cert: ' . substr($doc['certificate_uuid'], 0, 8) . '...', 0, 0, 'C');

        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetXY($sx, $sy + 24);
        $pdf->Cell($sw, 4, 'RSA-4096 / SHA-256', 0, 0, 'C');

        $pdf->SetXY($sx, $sy + 29);
        $pdf->Cell($sw, 4, 'notarize.onrite.cloud', 0, 0, 'C');

        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor($gR, $gG, $gB);
        $pdf->SetXY($sx, $sy + 34);
        $pdf->Cell($sw, 5, 'DIGITALLY CERTIFIED', 0, 0, 'C');
    }

    private function fmtBytes(int $b): string
    {
        if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
        if ($b >= 1024)    return round($b / 1024, 1) . ' KB';
        return $b . ' B';
    }

    private function clip(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }
}
