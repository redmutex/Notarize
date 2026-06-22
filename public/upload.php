<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$results = [];  // ['name' => ..., 'id' => ..., 'uuid' => ..., 'error' => ...]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $results[] = ['name' => '', 'error' => 'Invalid request. Please try again.'];
    } elseif (empty($_FILES['documents'])) {
        $results[] = ['name' => '', 'error' => 'No files received.'];
    } else {
        $notarize = new Notarize();
        $files    = $_FILES['documents'];
        $count    = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $result = $notarize->notarize($authUser['id'], $file);
            $results[] = array_merge(['name' => $file['name']], $result);
        }

        $successCount = count(array_filter($results, fn($r) => isset($r['id'])));
        if ($successCount > 0 && $count === 1) {
            // Single file: go straight to certificate
            redirect('/document.php?id=' . $results[0]['id'] . '&new=1');
        }
    }
}

$pageTitle = 'New Notarization';
require '../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <h2 class="fw-bold mb-1">
                <i class="bi bi-shield-plus me-2 text-primary"></i>New Notarization
            </h2>
            <p class="text-muted mb-4">
                Upload one or more documents. Each file will be hashed with SHA-256,
                signed with an RSA-4096 key, and packaged into a verifiable notarized PDF.
            </p>

            <?php if (!empty($results)): ?>
                <?php $successes = array_filter($results, fn($r) => isset($r['id'])); ?>
                <?php $failures  = array_filter($results, fn($r) => isset($r['error'])); ?>

                <?php if ($successes): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-patch-check-fill me-2"></i>
                        <strong><?= count($successes) ?> document<?= count($successes) !== 1 ? 's' : '' ?> notarized.</strong>
                        Certificates are ready below.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <div class="card border-0 shadow-sm mb-4">
                        <ul class="list-group list-group-flush">
                        <?php foreach ($successes as $r): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                                <span>
                                    <i class="bi bi-file-earmark-check text-success me-2"></i>
                                    <span class="fw-semibold"><?= h($r['name']) ?></span>
                                </span>
                                <a href="/document.php?id=<?= (int)$r['id'] ?>"
                                   class="btn btn-sm btn-gold">
                                    <i class="bi bi-patch-check me-1"></i>Open Certificate
                                </a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php foreach ($failures as $r): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php if ($r['name']): ?><strong><?= h($r['name']) ?>:</strong> <?php endif; ?>
                        <?= h($r['error']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select Documents</label>
                            <div class="upload-zone" id="dropZone">
                                <input type="file" name="documents[]" id="fileInput" class="d-none"
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.txt"
                                       multiple required>
                                <div id="dropZoneContent">
                                    <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                    <p class="mb-1 fw-semibold">Drag & drop or click to select</p>
                                    <p class="text-muted small mb-0">
                                        PDF, JPEG, PNG, GIF, WebP, DOC, DOCX, TXT &mdash; max 10 MB each &mdash;
                                        <strong>multiple files supported</strong>
                                    </p>
                                </div>
                                <div id="fileListWrap" class="d-none">
                                    <i class="bi bi-files upload-icon text-primary"></i>
                                    <ul id="fileList" class="list-unstyled mb-0 mt-2 small text-start" style="max-height:180px;overflow-y:auto"></ul>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info small py-2 mb-4">
                            <i class="bi bi-lock-fill me-2"></i>
                            Files are stored securely on the server and never shared.
                            Your notarized PDF is ready immediately after upload.
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn" disabled>
                            <i class="bi bi-patch-check me-2"></i><span id="submitLabel">Select files to continue</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    const zone        = document.getElementById('dropZone');
    const input       = document.getElementById('fileInput');
    const dzContent   = document.getElementById('dropZoneContent');
    const fileListWrap = document.getElementById('fileListWrap');
    const fileList    = document.getElementById('fileList');
    const submitBtn   = document.getElementById('submitBtn');
    const submitLabel = document.getElementById('submitLabel');

    zone.addEventListener('click', e => { if (e.target === zone || zone.contains(e.target)) input.click(); });
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            showFiles(e.dataTransfer.files);
        }
    });

    input.addEventListener('change', () => { if (input.files.length) showFiles(input.files); });

    function showFiles(files) {
        fileList.innerHTML = '';
        let totalSize = 0;
        Array.from(files).forEach(f => {
            totalSize += f.size;
            const li = document.createElement('li');
            li.className = 'py-1 border-bottom d-flex align-items-center gap-2';
            li.innerHTML = '<i class="bi bi-file-earmark-text text-primary"></i>'
                         + '<span class="flex-grow-1 text-truncate">' + escHtml(f.name) + '</span>'
                         + '<span class="text-muted text-nowrap">' + formatBytes(f.size) + '</span>';
            fileList.appendChild(li);
        });
        dzContent.classList.add('d-none');
        fileListWrap.classList.remove('d-none');

        const n = files.length;
        submitLabel.textContent = 'Notarize ' + n + ' document' + (n !== 1 ? 's' : '');
        submitBtn.disabled = false;
    }

    function formatBytes(b) {
        if (b >= 1048576) return (b/1048576).toFixed(2) + ' MB';
        if (b >= 1024)    return (b/1024).toFixed(1) + ' KB';
        return b + ' B';
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.getElementById('uploadForm').addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Notarizing…';
    });
})();
</script>

<?php require '../templates/footer.php'; ?>
