<?php
declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    private function make(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet  = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        if (MAIL_DRIVER === 'smtp') {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->isSendmail();
        }

        return $mail;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        try {
            $mail = $this->make();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject  = $subject;
            $mail->isHTML(true);
            $mail->Body     = $this->wrap($subject, $htmlBody);
            $mail->AltBody  = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            return $mail->send();
        } catch (\Throwable $e) {
            error_log('[Notarize Mailer] ' . $e->getMessage());
            return false;
        }
    }

    private function wrap(string $title, string $body): string
    {
        $year  = date('Y');
        $url   = APP_URL;
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>{$title}</title>
        </head>
        <body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:40px 0">
            <tr><td align="center">
              <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
                <!-- Header -->
                <tr>
                  <td style="background:#1a3a5c;padding:28px 36px;text-align:center">
                    <span style="color:#c9a84c;font-size:22px;font-weight:bold">&#x1F512; Notarize</span>
                  </td>
                </tr>
                <!-- Body -->
                <tr>
                  <td style="padding:36px 36px 28px;color:#333;font-size:15px;line-height:1.6">
                    {$body}
                  </td>
                </tr>
                <!-- Footer -->
                <tr>
                  <td style="background:#f4f7fb;padding:20px 36px;text-align:center;font-size:12px;color:#999;border-top:1px solid #e9ecef">
                    &copy; {$year} Notarize &bull; <a href="{$url}" style="color:#1a3a5c;text-decoration:none">notarize.onrite.cloud</a><br>
                    <a href="https://help.redmutex.com/open.php?topicId=15" style="color:#1a3a5c;text-decoration:none">Help &amp; Support</a>
                  </td>
                </tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }

    // ── Pre-built email methods ────────────────────────────────────────

    public function sendVerification(string $email, string $name, string $token): bool
    {
        $link      = APP_URL . '/verify-email.php?token=' . urlencode($token);
        $safeName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeLink  = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $body = <<<HTML
        <h2 style="color:#1a3a5c;margin-top:0">Verify your email address</h2>
        <p>Hi {$safeName},</p>
        <p>Thanks for creating your Notarize account. Please confirm your email address to start notarizing documents.</p>
        <p style="text-align:center;margin:32px 0">
          <a href="{$safeLink}" style="background:#1a3a5c;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:bold;display:inline-block">
            Verify Email Address
          </a>
        </p>
        <p style="color:#666;font-size:13px">Or paste this link into your browser:<br>
          <a href="{$safeLink}" style="color:#1a3a5c">{$safeLink}</a>
        </p>
        <p style="color:#999;font-size:12px">If you did not create this account, you can ignore this email.</p>
        HTML;
        return $this->send($email, $name, 'Verify your Notarize email address', $body);
    }

    public function sendAdminReviewRequest(array $doc, array $user): bool
    {
        $adminUrl = APP_URL . '/admin/pending.php';
        $docName  = htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8');
        $userName = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $userEmail= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
        $docId    = (int)$doc['id'];
        $body     = <<<HTML
        <h2 style="color:#1a3a5c;margin-top:0">New document pending review</h2>
        <p>A user has submitted a document for notarization and is awaiting your review.</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px">
          <tr><td style="padding:8px 0;color:#666;width:130px">Document</td><td style="padding:8px 0;font-weight:bold">{$docName}</td></tr>
          <tr><td style="padding:8px 0;color:#666">Submitted by</td><td style="padding:8px 0">{$userName} &lt;{$userEmail}&gt;</td></tr>
          <tr><td style="padding:8px 0;color:#666">Document ID</td><td style="padding:8px 0;font-family:monospace">#{$docId}</td></tr>
        </table>
        <p style="text-align:center;margin:32px 0">
          <a href="{$adminUrl}" style="background:#1a3a5c;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:bold;display:inline-block">
            Review in Admin Panel
          </a>
        </p>
        HTML;
        if (!ADMIN_EMAIL) {
            error_log('[Notarize Mailer] ADMIN_EMAIL not configured — skipping admin review notification');
            return false;
        }
        return $this->send(ADMIN_EMAIL, 'Notarize Admin', 'New document pending review — ' . $doc['original_filename'], $body);
    }

    public function sendApprovalConfirmation(array $doc, array $user): bool
    {
        $verifyUrl = APP_URL . '/verify.php?uuid=' . urlencode($doc['certificate_uuid']);
        $docUrl    = APP_URL . '/document.php?id=' . (int)$doc['id'];
        $docName   = htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8');
        $userName  = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $body      = <<<HTML
        <h2 style="color:#1a3a5c;margin-top:0">Your document has been notarized ✓</h2>
        <p>Hi {$userName},</p>
        <p>Great news! Your document has been reviewed and notarized with a cryptographic certificate.</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px">
          <tr><td style="padding:8px 0;color:#666;width:130px">Document</td><td style="padding:8px 0;font-weight:bold">{$docName}</td></tr>
          <tr><td style="padding:8px 0;color:#666">Notarized at</td><td style="padding:8px 0">{$doc['notarized_at']} UTC</td></tr>
          <tr><td style="padding:8px 0;color:#666">Certificate ID</td><td style="padding:8px 0;font-family:monospace;font-size:12px">{$doc['certificate_uuid']}</td></tr>
        </table>
        <p style="text-align:center;margin:32px 0">
          <a href="{$docUrl}" style="background:#c9a84c;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:bold;display:inline-block">
            Download Certificate
          </a>
        </p>
        <p style="text-align:center;font-size:13px;color:#666">
          Public verification: <a href="{$verifyUrl}" style="color:#1a3a5c">{$verifyUrl}</a>
        </p>
        HTML;
        return $this->send($user['email'], $user['name'], 'Your document has been notarized — ' . $doc['original_filename'], $body);
    }

    public function sendRejectionNotice(array $doc, array $user, string $notes): bool
    {
        $resubmitUrl = APP_URL . '/upload.php';
        $docName     = htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8');
        $userName    = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $safeNotes   = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
        $body        = <<<HTML
        <h2 style="color:#c0392b;margin-top:0">Document review — action required</h2>
        <p>Hi {$userName},</p>
        <p>After reviewing your submission, we were unable to complete the notarization of <strong>{$docName}</strong>.</p>
        <div style="background:#fff8f8;border-left:4px solid #e74c3c;padding:16px;margin:20px 0;border-radius:0 4px 4px 0">
          <strong style="color:#c0392b">Reason:</strong><br>
          <span style="color:#555">{$safeNotes}</span>
        </div>
        <p>You are welcome to correct the issue and resubmit your document.</p>
        <p style="text-align:center;margin:32px 0">
          <a href="{$resubmitUrl}" style="background:#1a3a5c;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:bold;display:inline-block">
            Submit a New Document
          </a>
        </p>
        <p style="color:#999;font-size:12px">
          If you have questions, please contact us via the Help &amp; Support link below.
        </p>
        HTML;
        return $this->send($user['email'], $user['name'], 'Action required — document review for ' . $doc['original_filename'], $body);
    }
}
