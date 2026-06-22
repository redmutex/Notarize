<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
$auth     = new Auth();
$authUser = $auth->user();
$pageTitle = 'Digital Document Notarization';
require '../templates/header.php';
?>

<!-- Hero -->
<section class="hero-section py-5">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-gold text-dark mb-3 px-3 py-2">
                    <i class="bi bi-shield-lock me-1"></i>Cryptographically Secure
                </span>
                <h1 class="display-4 fw-bold text-white mb-4">
                    Notarize Your Documents<br>
                    <span class="text-gold">Digitally.</span>
                </h1>
                <p class="lead text-white-75 mb-4">
                    Secure, verifiable digital notarization using RSA-4096 signatures.
                    Each document gets a unique certificate with a QR code anyone can scan to verify its authenticity.
                </p>
                <?php if ($authUser): ?>
                    <a href="/upload.php" class="btn btn-gold btn-lg px-4 me-2">
                        <i class="bi bi-cloud-upload me-2"></i>Notarize a Document
                    </a>
                    <a href="/dashboard.php" class="btn btn-outline-light btn-lg px-4">
                        My Documents
                    </a>
                <?php else: ?>
                    <a href="/register.php" class="btn btn-gold btn-lg px-4 me-2">
                        <i class="bi bi-person-plus me-2"></i>Get Started Free
                    </a>
                    <a href="/login.php" class="btn btn-outline-light btn-lg px-4">Sign In</a>
                <?php endif; ?>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block">
                <div class="hero-cert-preview">
                    <i class="bi bi-patch-check-fill text-gold" style="font-size: 9rem; opacity:.9;"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="py-5 bg-light">
    <div class="container py-3">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Why Notarize?</h2>
            <p class="text-muted">Proof that your document existed, unchanged, at a specific point in time.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card card h-100 border-0 shadow-sm p-4 text-center">
                    <i class="bi bi-key-fill feature-icon text-primary mb-3"></i>
                    <h5 class="fw-bold">RSA-4096 Signatures</h5>
                    <p class="text-muted mb-0">Every document is signed with a 4096-bit RSA private key, producing a tamper-evident cryptographic seal.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card card h-100 border-0 shadow-sm p-4 text-center">
                    <i class="bi bi-qr-code feature-icon text-primary mb-3"></i>
                    <h5 class="fw-bold">QR Verification</h5>
                    <p class="text-muted mb-0">Each certificate includes a QR code. Anyone can scan it to instantly verify the document's authenticity online.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card card h-100 border-0 shadow-sm p-4 text-center">
                    <i class="bi bi-clock-history feature-icon text-primary mb-3"></i>
                    <h5 class="fw-bold">Timestamped Proof</h5>
                    <p class="text-muted mb-0">Notarization timestamps are permanently recorded, proving the document existed in its current form at that exact moment.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="py-5" id="how-it-works">
    <div class="container py-3">
        <div class="text-center mb-5">
            <h2 class="fw-bold">How It Works</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4 text-center">
                <div class="step-number">1</div>
                <h5 class="fw-bold mt-3">Upload Your Document</h5>
                <p class="text-muted">Upload any PDF, image, or Word file. We support files up to 10 MB.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="step-number">2</div>
                <h5 class="fw-bold mt-3">We Sign &amp; Certify</h5>
                <p class="text-muted">We compute a SHA-256 hash and sign it with our RSA-4096 private key, creating a unique digital seal.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="step-number">3</div>
                <h5 class="fw-bold mt-3">Get Your Certificate</h5>
                <p class="text-muted">Download your notarization certificate with embedded QR code. Share it as proof of your document's authenticity.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<?php if (!$authUser): ?>
<section class="py-5 cta-section text-white text-center">
    <div class="container py-3">
        <h2 class="fw-bold mb-3">Ready to notarize?</h2>
        <p class="lead mb-4 text-white-75">Create your free account and notarize your first document in minutes.</p>
        <a href="/register.php" class="btn btn-gold btn-lg px-5">
            <i class="bi bi-person-plus me-2"></i>Create Free Account
        </a>
    </div>
</section>
<?php endif; ?>

<?php require '../templates/footer.php'; ?>
