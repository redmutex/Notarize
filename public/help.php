<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;

$auth     = new Auth();
$authUser = $auth->user();

$pageTitle = 'Help & Support';
require '../templates/header.php';
?>

<div class="container py-5" style="max-width:820px">

    <div class="text-center mb-5">
        <i class="bi bi-question-circle-fill text-primary" style="font-size:3.5rem"></i>
        <h2 class="fw-bold mt-3 mb-1">Help &amp; Support</h2>
        <p class="text-muted">Answers to common questions about Notarize</p>
    </div>

    <!-- Contact card -->
    <div class="card border-primary border-2 shadow-sm mb-5">
        <div class="card-body p-4 d-flex flex-wrap align-items-center gap-4">
            <div class="flex-grow-1">
                <h5 class="fw-bold mb-1"><i class="bi bi-headset me-2 text-primary"></i>Still have questions?</h5>
                <p class="text-muted mb-0">
                    Our support team typically responds within a few hours during business hours.
                </p>
            </div>
            <a href="https://help.redmutex.com/open.php?topicId=15" target="_blank" rel="noopener"
               class="btn btn-primary btn-lg flex-shrink-0">
                <i class="bi bi-headset me-2"></i>Get Support
            </a>
        </div>
    </div>

    <!-- FAQ -->
    <h4 class="fw-bold mb-3">Frequently Asked Questions</h4>

    <div class="accordion" id="faqAccordion">

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq1">
                    What is Notarize and what does it do?
                </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    Notarize is a digital document authentication platform. You upload a document, verify your identity,
                    and our team cryptographically signs it with an RSA-4096 private key. The resulting certificate
                    contains a SHA-256 hash of your file and a digital signature that anyone can verify using the
                    public key — proving the document has not been altered since notarization.
                </div>
            </div>
        </div>

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq2">
                    Why do I need to upload a photo ID and selfie?
                </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    Identity verification is a core requirement for digital notarization. We require a government-issued
                    photo ID (passport, national ID, or driver's licence) and a selfie of you holding that ID so our
                    team can confirm the document was submitted by the person named in it.
                    Your ID and selfie are stored securely, are never shared with third parties, and are not included
                    in the issued certificate.
                </div>
            </div>
        </div>

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq3">
                    How long does the review take?
                </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    Our team typically reviews submissions within <strong>24 hours</strong>.
                    Once approved, you will receive an email with a download link for the notarized PDF
                    and a unique verification URL you can share with anyone.
                </div>
            </div>
        </div>

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq4">
                    What file types can I notarize?
                </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    We accept PDF, JPEG, PNG, GIF, WebP, Microsoft Word (.doc / .docx), and plain text (.txt) files
                    up to 10 MB. For best results, use PDF for formal documents — the notarized output is always
                    delivered as a PDF with the certificate embedded.
                </div>
            </div>
        </div>

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq5">
                    How do I verify a notarized document?
                </button>
            </h2>
            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    Anyone — with or without an account — can verify a document at
                    <a href="/verify.php">notarize.onrite.cloud/verify.php</a>.
                    Enter the certificate UUID or scan the QR code from the notarized PDF.
                    The verification page re-checks the RSA signature against the original file hash and displays
                    whether the certificate is authentic and the document has not been altered.
                </div>
            </div>
        </div>

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq6">
                    What is the difference between Lite, Pro, Elite, and Pay As You Go?
                </button>
            </h2>
            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    <ul class="mb-0">
                        <li><strong>Lite (Free)</strong> — 1 document per month, perfect for trying the service.</li>
                        <li><strong>Pro ($10/mo)</strong> — 10 documents per month for regular users.</li>
                        <li><strong>Elite ($50/mo)</strong> — 100 documents per month for high-volume users.</li>
                        <li><strong>Pay As You Go ($3/document)</strong> — no subscription, pay only for what you use.</li>
                    </ul>
                    Subscriptions are billed monthly and can be cancelled at any time.
                    See <a href="/plans.php">Plans &amp; Pricing</a> for more detail.
                </div>
            </div>
        </div>

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq7">
                    Why was my submission rejected?
                </button>
            </h2>
            <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    Common reasons for rejection include: photo ID not clearly legible, selfie does not show both
                    your face and ID together, the uploaded document is corrupted or password-protected, or the
                    information on the ID does not match your account name.
                    You will receive an email with the specific reason.
                    Simply resubmit with corrected files — your plan quota is not deducted for rejected submissions.
                </div>
            </div>
        </div>

        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq8">
                    Is my data secure?
                </button>
            </h2>
            <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted">
                    Yes. Documents are stored outside the web root on encrypted servers accessible only to authorised
                    processes. The RSA-4096 signing key is stored on the server in a path inaccessible from the web.
                    All traffic is encrypted with TLS. We never sell or share your documents or personal data with
                    third parties.
                </div>
            </div>
        </div>

    </div>

    <!-- Bottom CTA -->
    <div class="text-center mt-5">
        <p class="text-muted mb-3">Didn't find what you were looking for?</p>
        <a href="https://help.redmutex.com/open.php?topicId=15" target="_blank" rel="noopener"
           class="btn btn-outline-primary btn-lg">
            <i class="bi bi-headset me-2"></i>Contact Support
        </a>
    </div>

</div>

<?php require '../templates/footer.php'; ?>
