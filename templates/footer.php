</main>

<footer class="footer mt-auto py-4 bg-primary-dark">
    <div class="container">
        <div class="row align-items-center gy-2">
            <div class="col-md-5">
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="bi bi-shield-lock-fill text-gold"></i>
                    <strong>Notarize</strong>
                    <span class="text-white-50 small">— Cryptographic Document Authentication</span>
                </div>
            </div>
            <div class="col-md-7 text-md-end">
                <span class="text-white-50 small me-3">
                    &copy; <?= date('Y') ?> Notarize
                </span>
                <a href="/verify.php" class="text-white-50 small me-3 text-decoration-none">
                    <i class="bi bi-shield-check me-1"></i>Verify a Document
                </a>
                <a href="/help.php" class="text-white-50 small me-3 text-decoration-none">
                    <i class="bi bi-question-circle me-1"></i>Help
                </a>
                <a href="https://help.redmutex.com/open.php?topicId=15" target="_blank" rel="noopener"
                   class="text-white-50 small me-3 text-decoration-none">
                    <i class="bi bi-headset me-1"></i>Get Support
                </a>
                <a href="/login.php" class="text-white-50 small text-decoration-none">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                </a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
