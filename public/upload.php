<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($_FILES['document'])) {
        $error = 'No file received.';
    } else {
        $notarize = new Notarize();
        $result   = $notarize->notarize($authUser['id'], $_FILES['document']);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            redirect('/document.php?id=' . $result['id'] . '&new=1');
        }
    }
}

$pageTitle = 'Notarize a Document';
require '../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <h2 class="fw-bold mb-1">
                <i class="bi bi-cloud-upload me-2 text-primary"></i>Notarize a Document
            </h2>
            <p class="text-muted mb-4">
                Upload your document to receive a cryptographically signed certificate of notarization.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= h($error) ?>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select Document</label>
                            <div class="upload-zone" id="dropZone">
                                <input type="file" name="document" id="fileInput" class="d-none"
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.txt"
                                       required>
                                <div id="dropZoneContent">
                                    <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                    <p class="mb-1 fw-semibold">Drag & drop or click to select</p>
                                    <p class="text-muted small mb-0">PDF, JPEG, PNG, GIF, WebP, DOC, DOCX, TXT &mdash; max 10 MB</p>
                                </div>
                                <div id="fileSelected" class="d-none">
                                    <i class="bi bi-file-earmark-check text-success" style="font-size:2rem"></i>
                                    <p class="mb-1 fw-semibold" id="fileName"></p>
                                    <p class="text-muted small mb-0" id="fileSize"></p>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info small py-2 mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>What happens next:</strong> We compute a SHA-256 hash of your file and sign it with our RSA-4096 private key. The file is stored securely and never shared.
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">
                            <i class="bi bi-patch-check me-2"></i>Notarize Document
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    const zone      = document.getElementById('dropZone');
    const input     = document.getElementById('fileInput');
    const content   = document.getElementById('dropZoneContent');
    const selected  = document.getElementById('fileSelected');
    const fileNameEl = document.getElementById('fileName');
    const fileSizeEl = document.getElementById('fileSize');
    const submitBtn = document.getElementById('submitBtn');

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            showFile(e.dataTransfer.files[0]);
        }
    });

    input.addEventListener('change', () => { if (input.files.length) showFile(input.files[0]); });

    function showFile(f) {
        fileNameEl.textContent = f.name;
        fileSizeEl.textContent = formatBytes(f.size);
        content.classList.add('d-none');
        selected.classList.remove('d-none');
    }

    function formatBytes(b) {
        if (b >= 1048576) return (b/1048576).toFixed(2) + ' MB';
        if (b >= 1024)    return (b/1024).toFixed(1) + ' KB';
        return b + ' B';
    }

    document.getElementById('uploadForm').addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Notarizing…';
    });
})();
</script>

<?php require '../templates/footer.php'; ?>
