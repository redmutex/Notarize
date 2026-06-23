<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Billing;
use App\Database;

$auth     = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

// Re-query DB for admin status — never trust session alone for privileged access
$_db     = Database::getInstance();
$_stmt   = $_db->prepare("SELECT is_admin FROM users WHERE id = ?");
$_stmt->execute([$authUser['id']]);
$isAdmin = (bool)$_stmt->fetchColumn();

$id      = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Invalid request.');
}

$billing = new Billing();
$txn     = $billing->getTransaction($id);

if (!$txn) {
    http_response_code(404);
    exit('Transaction not found.');
}

// Access control: only the owner or an admin may download their invoice
if ((int)$txn['user_id'] !== $authUser['id'] && !$isAdmin) {
    http_response_code(403);
    exit('Forbidden.');
}

// ── Build invoice data ────────────────────────────────────────────

$invoiceNo  = 'INV-' . str_pad((string)$txn['id'], 6, '0', STR_PAD_LEFT);
$invoiceDate = date('F j, Y', strtotime($txn['created_at']));
$planInfo   = Billing::PLANS[$txn['plan_type']] ?? ['name' => strtoupper($txn['plan_type'])];

if ($txn['status'] === 'subscription_activated') {
    $description = $planInfo['name'] . ' Plan — Monthly Subscription Activated';
    $period      = 'Monthly billing period starting ' . date('F j, Y', strtotime($txn['created_at']));
} elseif ($txn['status'] === 'completed' && $txn['plan_type'] === 'payg') {
    $description = 'Pay As You Go — 1 Document Notarization';
    $period      = 'One-time charge on ' . $invoiceDate;
} else {
    $description = $planInfo['name'] . ' Plan — ' . ucfirst($txn['status']);
    $period      = date('F j, Y', strtotime($txn['created_at']));
}

$amount   = (float)$txn['amount'];
$currency = $txn['currency'] ?? 'USD';
$paypalRef = $txn['paypal_txn_id'] ?? $txn['paypal_subscription'] ?? 'N/A';

// ── Generate PDF ──────────────────────────────────────────────────

class InvoicePDF extends TCPDF
{
    public function Header() {}
    public function Footer() {}
}

$pdf = new InvoicePDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Notarize');
$pdf->SetAuthor('Notarize');
$pdf->SetTitle($invoiceNo);
$pdf->SetSubject('Invoice for ' . $txn['user_name']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$primaryHex  = [26, 58, 92];   // #1a3a5c
$goldHex     = [201, 168, 76]; // #c9a84c
$lightGrey   = [248, 249, 250];
$midGrey     = [108, 117, 125];
$darkText    = [33, 37, 41];

$pageW   = $pdf->getPageWidth() - 40; // content width
$leftX   = 20;
$rightX  = 130; // right column start

// ── Logo / Header bar ─────────────────────────────────────────────

$pdf->SetFillColor(...$primaryHex);
$pdf->Rect(0, 0, $pdf->getPageWidth(), 28, 'F');

$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(20, 8);
$pdf->Cell(0, 8, chr(0xF0) . ' Notarize', 0, 0, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(200, 220, 240);
$pdf->SetXY(20, 17);
$pdf->Cell(0, 6, 'notarize.onrite.cloud', 0, 0, 'L');

// Invoice title (right side of header)
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(...$goldHex);
$pdf->SetXY(0, 7);
$pdf->Cell($pdf->getPageWidth() - 20, 8, 'INVOICE', 0, 0, 'R');

// ── Invoice meta ──────────────────────────────────────────────────

$pdf->SetTextColor(...$darkText);
$pdf->SetY(38);

// Left: bill to
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(...$midGrey);
$pdf->SetX($leftX);
$pdf->Cell(80, 5, 'BILL TO', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(...$darkText);
$pdf->SetX($leftX);
$pdf->Cell(80, 6, $txn['user_name'], 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(...$midGrey);
$pdf->SetX($leftX);
$pdf->Cell(80, 5, $txn['user_email'], 0, 1, 'L');

// Right: invoice details
$metaY = 38;
$labelW = 38;
$valW   = 55;

foreach ([
    ['INVOICE NUMBER', $invoiceNo],
    ['DATE',           $invoiceDate],
    ['STATUS',         'PAID'],
    ['CURRENCY',       $currency],
] as $row) {
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(...$midGrey);
    $pdf->SetXY($rightX, $metaY);
    $pdf->Cell($labelW, 5, $row[0], 0, 0, 'L');

    $pdf->SetFont('helvetica', $row[0] === 'STATUS' ? 'B' : '', 9);
    $pdf->SetTextColor(...($row[0] === 'STATUS' ? [40, 167, 69] : $darkText));
    $pdf->SetXY($rightX + $labelW, $metaY);
    $pdf->Cell($valW, 5, $row[1], 0, 0, 'L');
    $metaY += 7;
}

// ── Divider ───────────────────────────────────────────────────────

$pdf->SetY(74);
$pdf->SetDrawColor(...$primaryHex);
$pdf->SetLineWidth(0.5);
$pdf->Line($leftX, $pdf->GetY(), $leftX + $pageW, $pdf->GetY());

// ── Line items header ─────────────────────────────────────────────

$pdf->SetY($pdf->GetY() + 4);
$pdf->SetFillColor(...$lightGrey);
$pdf->SetTextColor(...$primaryHex);
$pdf->SetFont('helvetica', 'B', 9);
$rowY = $pdf->GetY();
$pdf->SetX($leftX);
$pdf->SetFillColor(...$primaryHex);
$pdf->Rect($leftX, $rowY, $pageW, 8, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY($leftX + 2, $rowY + 1.5);
$pdf->Cell(120, 5, 'DESCRIPTION', 0, 0, 'L');
$pdf->SetXY($leftX + 122, $rowY + 1.5);
$pdf->Cell(38, 5, 'AMOUNT', 0, 0, 'R');
$pdf->SetY($rowY + 8);

// ── Line item row ─────────────────────────────────────────────────

$pdf->SetFillColor(255, 255, 255);
$itemY = $pdf->GetY() + 3;

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(...$darkText);
$pdf->SetXY($leftX + 2, $itemY);
$pdf->Cell(118, 6, $description, 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY($leftX + 122, $itemY);
$pdf->Cell(38, 6, '$' . number_format($amount, 2), 0, 0, 'R');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(...$midGrey);
$pdf->SetXY($leftX + 2, $itemY + 7);
$pdf->Cell(118, 5, $period, 0, 0, 'L');

$pdf->SetY($itemY + 14);
$pdf->SetDrawColor(222, 226, 230);
$pdf->SetLineWidth(0.3);
$pdf->Line($leftX, $pdf->GetY(), $leftX + $pageW, $pdf->GetY());

// ── Totals ────────────────────────────────────────────────────────

$totY = $pdf->GetY() + 4;

foreach ([
    ['Subtotal', '$' . number_format($amount, 2)],
    ['Tax',      '$0.00'],
] as $tRow) {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(...$midGrey);
    $pdf->SetXY($leftX + 90, $totY);
    $pdf->Cell(30, 6, $tRow[0], 0, 0, 'R');
    $pdf->SetTextColor(...$darkText);
    $pdf->SetXY($leftX + 122, $totY);
    $pdf->Cell(38, 6, $tRow[1], 0, 0, 'R');
    $totY += 7;
}

$pdf->SetDrawColor(...$primaryHex);
$pdf->SetLineWidth(0.5);
$pdf->Line($leftX + 90, $totY, $leftX + $pageW, $totY);
$totY += 3;

$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(...$primaryHex);
$pdf->SetXY($leftX + 90, $totY);
$pdf->Cell(30, 7, 'TOTAL', 0, 0, 'R');
$pdf->SetXY($leftX + 122, $totY);
$pdf->Cell(38, 7, '$' . number_format($amount, 2) . ' ' . $currency, 0, 0, 'R');

// ── Payment details ───────────────────────────────────────────────

$pdf->SetY($totY + 16);
$pdf->SetFillColor(...$lightGrey);
$boxY = $pdf->GetY();
$pdf->SetFillColor(248, 249, 250);
$pdf->RoundedRect($leftX, $boxY, $pageW, 22, 2, '1111', 'F');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(...$primaryHex);
$pdf->SetXY($leftX + 4, $boxY + 4);
$pdf->Cell(0, 5, 'PAYMENT INFORMATION', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(...$darkText);
$pdf->SetXY($leftX + 4, $boxY + 10);
$pdf->Cell(40, 5, 'Payment method:', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'PayPal', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY($leftX + 4, $boxY + 16);
$pdf->Cell(40, 5, 'Reference ID:', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(...$midGrey);
$pdf->Cell(0, 5, $paypalRef, 0, 1, 'L');

// ── Footer ────────────────────────────────────────────────────────

$pdf->SetY($pdf->getPageHeight() - 28);
$pdf->SetDrawColor(...$primaryHex);
$pdf->SetLineWidth(0.5);
$pdf->Line($leftX, $pdf->GetY(), $leftX + $pageW, $pdf->GetY());

$pdf->SetY($pdf->GetY() + 3);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(...$midGrey);
$pdf->SetX($leftX);
$pdf->MultiCell($pageW, 4,
    "Notarize — notarize.onrite.cloud\n" .
    "For support visit help.redmutex.com/open.php?topicId=15  |  This document serves as your official receipt.",
    0, 'C'
);

// ── Output ────────────────────────────────────────────────────────

$filename = $invoiceNo . '_Notarize.pdf';
$pdf->Output($filename, 'I'); // 'I' = inline in browser, 'D' = force download
exit;
