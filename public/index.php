<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
$auth     = new Auth();
$authUser = $auth->user();
$pageTitle = 'Digital Document Authentication';
require '../templates/header.php';
?>

<!-- Hero -->
<section class="hero-section py-5">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-gold text-dark mb-3 px-3 py-2 fw-semibold">
                    <i class="bi bi-shield-lock me-1"></i>RSA-4096 &bull; SHA-256 &bull; QR-Verifiable
                </span>
                <h1 class="display-4 fw-bold text-white mb-4 lh-sm">
                    Seal your documents<br>
                    <span class="text-gold">with cryptographic proof.</span>
                </h1>
                <p class="lead text-white-75 mb-5">
                    Upload any document and receive a digitally signed certificate
                    that proves its authenticity and exact content at a specific moment in time.
                    Anyone can verify it — no account required.
                </p>
                <?php if ($authUser): ?>
                    <a href="/upload.php" class="btn btn-gold btn-lg px-4 me-2">
                        <i class="bi bi-plus-circle me-2"></i>Notarize a Document
                    </a>
                    <a href="/dashboard.php" class="btn btn-outline-light btn-lg px-4">
                        <i class="bi bi-folder2-open me-2"></i>My Documents
                    </a>
                <?php else: ?>
                    <a href="/register.php" class="btn btn-gold btn-lg px-4 me-2">
                        <i class="bi bi-person-plus me-2"></i>Start for Free
                    </a>
                    <a href="/verify.php" class="btn btn-outline-light btn-lg px-4">
                        <i class="bi bi-shield-check me-2"></i>Verify a Document
                    </a>
                <?php endif; ?>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block">
                <div style="position:relative;display:inline-block">
                    <i class="bi bi-shield-lock-fill text-gold" style="font-size:9rem;opacity:.15;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%)"></i>
                    <i class="bi bi-patch-check-fill text-gold" style="font-size:9rem;opacity:.9"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="py-5 bg-light">
    <div class="container py-3">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Built on cryptographic trust</h2>
            <p class="text-muted mx-auto" style="max-width:520px">
                Every notarization creates a mathematically verifiable proof that
                your document existed, unchanged, at a specific point in time.
            </p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card card h-100 border-0 shadow-sm p-4 text-center">
                    <i class="bi bi-key-fill feature-icon text-primary mb-3"></i>
                    <h5 class="fw-bold">RSA-4096 Cryptography</h5>
                    <p class="text-muted mb-0">
                        Each document is hashed with SHA-256 and signed with a 4096-bit RSA key,
                        producing a tamper-evident seal. Any change to the file — even a single byte —
                        invalidates the signature.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card card h-100 border-0 shadow-sm p-4 text-center">
                    <i class="bi bi-qr-code feature-icon text-primary mb-3"></i>
                    <h5 class="fw-bold">Instant QR Verification</h5>
                    <p class="text-muted mb-0">
                        Every certificate includes a QR code that links to a public verification page.
                        Third parties can confirm authenticity in seconds — no account needed.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card card h-100 border-0 shadow-sm p-4 text-center">
                    <i class="bi bi-file-earmark-lock2-fill feature-icon text-primary mb-3"></i>
                    <h5 class="fw-bold">Complete Notarized Package</h5>
                    <p class="text-muted mb-0">
                        Download a single PDF containing your original document, the certificate
                        of notarization, and the digital signature printed in the footer of every page.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="py-5" id="how-it-works">
    <div class="container py-3">
        <div class="text-center mb-5">
            <h2 class="fw-bold">How it works</h2>
            <p class="text-muted">Three steps from upload to verified certificate.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4 text-center">
                <div class="step-number mx-auto">1</div>
                <h5 class="fw-bold mt-3">Upload Your Document</h5>
                <p class="text-muted">
                    Submit any PDF, image, or Word file up to 10 MB.
                    Multiple files can be notarized at once.
                </p>
            </div>
            <div class="col-md-4 text-center">
                <div class="step-number mx-auto">2</div>
                <h5 class="fw-bold mt-3">Cryptographic Sealing</h5>
                <p class="text-muted">
                    A SHA-256 fingerprint of your file is computed and
                    signed with an RSA-4096 private key, creating a unique,
                    unforgeable seal tied to this exact version of the document.
                </p>
            </div>
            <div class="col-md-4 text-center">
                <div class="step-number mx-auto">3</div>
                <h5 class="fw-bold mt-3">Download &amp; Share</h5>
                <p class="text-muted">
                    Receive a complete notarized PDF — original pages plus certificate —
                    with a QR code anyone can scan to independently verify authenticity.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Accepted formats strip -->
<section class="py-4 bg-light border-top border-bottom">
    <div class="container text-center">
        <p class="text-muted small mb-2 fw-semibold text-uppercase letter-spacing-wide">Supported file types</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php foreach (['PDF', 'JPEG', 'PNG', 'GIF', 'WebP', 'Word (.docx)', 'Plain Text'] as $fmt): ?>
                <span class="badge bg-white border text-secondary fw-normal px-3 py-2 shadow-sm"><?= $fmt ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<?php if (!$authUser): ?>
<section class="py-5 cta-section text-white text-center">
    <div class="container py-3">
        <i class="bi bi-shield-lock-fill text-gold mb-3 d-block" style="font-size:3rem;opacity:.8"></i>
        <h2 class="fw-bold mb-3">Your documents deserve proof.</h2>
        <p class="lead mb-4 text-white-75 mx-auto" style="max-width:500px">
            Create a free account and notarize your first document in under a minute.
        </p>
        <a href="/register.php" class="btn btn-gold btn-lg px-5">
            <i class="bi bi-person-plus me-2"></i>Create Free Account
        </a>
        <p class="mt-3 mb-0">
            <a href="/verify.php" class="text-white-50 small">
                Already have a certificate? Verify it here.
            </a>
        </p>
    </div>
</section>
<?php endif; ?>

<?php require '../templates/footer.php'; ?>
