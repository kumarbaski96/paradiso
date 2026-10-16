<?php
session_start();

/* ================= DATABASE CONNECTION ================= */
include 'config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

/*
    Project structure:
    project/
    ├── config/db.php
    ├── admin/manage_about.php
    └── uploads/about/
*/

// Upload directory on the server
$uploadDir = dirname(__DIR__) . '/uploads/about/';

// Image URL relative to this admin page
$uploadUrl = '../uploads/about/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        die('Unable to create uploads/about directory.');
    }
}

if (empty($_SESSION['about_csrf'])) {
    $_SESSION['about_csrf'] = bin2hex(random_bytes(32));
}

/* ================= HELPER FUNCTIONS ================= */

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectAbout($message, $type = 'success')
{
    $_SESSION['about_message'] = $message;
    $_SESSION['about_message_type'] = $type;

    header('Location: manage_about.php');
    exit;
}

function removeLocalAboutImage($path)
{
    global $uploadDir;

    if (empty($path)) {
        return;
    }

    // Never delete external images.
    $path = str_replace('\\', '/', $path);

    if (strpos($path, 'uploads/about/') === false) {
        return;
    }

    $filename = basename($path);

    // Only delete files directly inside the configured folder.
    $fullPath = $uploadDir . $filename;

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

function uploadAboutImage($field, $oldImage = '')
{
    global $uploadDir, $uploadUrl;

    if (
        !isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return $oldImage;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image size must not exceed 5 MB.');
    }

    $tmp = $_FILES[$field]['tmp_name'];

    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (
        !isset($allowed[$mime]) ||
        @getimagesize($tmp) === false
    ) {
        throw new RuntimeException(
            'Only JPG, PNG and WEBP images are allowed.'
        );
    }

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Cannot create image directory.');
        }
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Unable to save the uploaded image.');
    }

    // Remove the previous local image after successful upload.
    removeLocalAboutImage($oldImage);

    return $uploadUrl . $filename;
}

/* ================= FORM CONFIGURATION ================= */

$columns = [
    'section_label',
    'heading',
    'image',
    'image_alt',
    'description1',
    'description2',
    'feature1',
    'feature2',
    'feature3',
    'feature4',
    'stamp_text',
    'button_text',
    'button_link'
];

$editing = null;
$message = $_SESSION['about_message'] ?? '';
$messageType = $_SESSION['about_message_type'] ?? 'success';

unset($_SESSION['about_message'], $_SESSION['about_message_type']);

/* ================= POST OPERATIONS ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($token) ||
            !hash_equals($_SESSION['about_csrf'], $token)
        ) {
            throw new RuntimeException(
                'Invalid request token. Refresh the page and try again.'
            );
        }

        $action = $_POST['action'] ?? '';

        /* ---------- DELETE ---------- */

        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid record ID.');
            }

            $stmt = $conn->prepare(
                'SELECT image FROM about WHERE id = ?'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$record) {
                throw new RuntimeException('About record not found.');
            }

            $stmt = $conn->prepare('DELETE FROM about WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            removeLocalAboutImage($record['image']);

            redirectAbout('About record deleted successfully.');
        }

        /* ---------- ACTIVATE / DEACTIVATE ---------- */

        if ($action === 'toggle') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid record ID.');
            }

            $stmt = $conn->prepare(
                'UPDATE about
                 SET status = IF(status = 1, 0, 1)
                 WHERE id = ?'
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if (!$changed) {
                $stmt = $conn->prepare(
                    'SELECT id FROM about WHERE id = ?'
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if (!$exists) {
                    throw new RuntimeException('About record not found.');
                }
            }

            redirectAbout('About status updated successfully.');
        }

        /* ---------- ADD / UPDATE ---------- */

        if ($action === 'save') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id === false || $id === null || $id < 0) {
                throw new RuntimeException('Invalid record ID.');
            }

            $data = [];

            foreach ($columns as $column) {
                $value = $_POST[$column] ?? '';

                if (!is_string($value)) {
                    throw new RuntimeException('Invalid form data.');
                }

                $data[$column] = trim($value);
            }

            $required = [
                'section_label',
                'heading',
                'description1',
                'description2',
                'feature1',
                'feature2',
                'feature3',
                'feature4'
            ];

            foreach ($required as $field) {
                if ($data[$field] === '') {
                    throw new RuntimeException(
                        'Please fill in all required fields.'
                    );
                }
            }

            /* Validate button link */
            $link = $data['button_link'];

            if ($link !== '') {
                $isLocal = str_starts_with($link, '/') ||
                           str_starts_with($link, '#');

                $isHttps = filter_var($link, FILTER_VALIDATE_URL) &&
                           strtolower((string)parse_url(
                               $link,
                               PHP_URL_SCHEME
                           )) === 'https';

                if (!$isLocal && !$isHttps) {
                    throw new RuntimeException(
                        'Use a local path, a #section link, or an HTTPS URL.'
                    );
                }

                // Prevent protocol-relative external URLs.
                if (str_starts_with($link, '//')) {
                    throw new RuntimeException('Invalid button link.');
                }
            }

            /* Find existing record for edit */
            $oldImage = '';

            if ($id > 0) {
                $stmt = $conn->prepare(
                    'SELECT image FROM about WHERE id = ?'
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existing) {
                    throw new RuntimeException('About record not found.');
                }

                $oldImage = $existing['image'] ?? '';
            }

            /*
             * Uploaded file takes priority.
             * Otherwise use an explicitly supplied image URL.
             * If neither is provided on edit, retain the current image.
             */
            $data['image'] = uploadAboutImage('image_file', $oldImage);

            $uploaded = isset($_FILES['image_file']) &&
                $_FILES['image_file']['error'] === UPLOAD_ERR_OK;

            if (!$uploaded) {
                $imageUrl = trim($_POST['image_url'] ?? '');

                if ($imageUrl !== '') {
                    if (
                        !filter_var($imageUrl, FILTER_VALIDATE_URL) ||
                        strtolower((string)parse_url(
                            $imageUrl,
                            PHP_URL_SCHEME
                        )) !== 'https'
                    ) {
                        throw new RuntimeException(
                            'Image URL must be a valid HTTPS URL.'
                        );
                    }

                    // Replace the old local image only when switching to URL.
                    if ($id > 0 && $imageUrl !== $oldImage) {
                        removeLocalAboutImage($oldImage);
                    }

                    $data['image'] = $imageUrl;
                }
            }

            if ($data['image'] === '') {
                throw new RuntimeException(
                    'Please upload an image or provide an HTTPS image URL.'
                );
            }

            /* Save changes */
            if ($id > 0) {
                $sql = 'UPDATE about SET
                    section_label = ?,
                    heading = ?,
                    image = ?,
                    image_alt = ?,
                    description1 = ?,
                    description2 = ?,
                    feature1 = ?,
                    feature2 = ?,
                    feature3 = ?,
                    feature4 = ?,
                    stamp_text = ?,
                    button_text = ?,
                    button_link = ?
                    WHERE id = ?';

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    'sssssssssssssi',
                    $data['section_label'],
                    $data['heading'],
                    $data['image'],
                    $data['image_alt'],
                    $data['description1'],
                    $data['description2'],
                    $data['feature1'],
                    $data['feature2'],
                    $data['feature3'],
                    $data['feature4'],
                    $data['stamp_text'],
                    $data['button_text'],
                    $data['button_link'],
                    $id
                );

                $stmt->execute();
                $stmt->close();

                redirectAbout('About section updated successfully.');
            } else {
                $sql = 'INSERT INTO about (
                    section_label,
                    heading,
                    image,
                    image_alt,
                    description1,
                    description2,
                    feature1,
                    feature2,
                    feature3,
                    feature4,
                    stamp_text,
                    button_text,
                    button_link,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)';

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    'sssssssssssss',
                    $data['section_label'],
                    $data['heading'],
                    $data['image'],
                    $data['image_alt'],
                    $data['description1'],
                    $data['description2'],
                    $data['feature1'],
                    $data['feature2'],
                    $data['feature3'],
                    $data['feature4'],
                    $data['stamp_text'],
                    $data['button_text'],
                    $data['button_link']
                );

                $stmt->execute();
                $stmt->close();

                redirectAbout('About section added successfully.');
            }
        }

        throw new RuntimeException('Unknown action.');

    } catch (Throwable $ex) {
        redirectAbout($ex->getMessage(), 'danger');
    }
}

/* ================= LOAD EDIT RECORD ================= */

if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {
        $stmt = $conn->prepare('SELECT * FROM about WHERE id = ?');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$editing) {
            $message = 'About record not found.';
            $messageType = 'danger';
        }
    }
}

/* ================= LOAD ALL RECORDS ================= */

$result = $conn->query('SELECT * FROM about ORDER BY id DESC');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Manage About | Royal Marriage Hall</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      rel="stylesheet">

<style>
:root {
    --gold: #c59d5f;
    --gold-dark: #9e7539;
    --navy: #172238;
    --cream: #faf7f0;
}

body {
    background: #f3f5f9;
    color: #263247;
    font-family: Arial, sans-serif;
}

.page-header {
    background: linear-gradient(120deg, #172238, #344461);
    color: #fff;
    padding: 25px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 8px 25px rgba(23, 34, 56, .12);
}

.page-header p {
    color: #dce2ec;
}

.card {
    border: 0;
    border-radius: 13px;
    box-shadow: 0 4px 18px rgba(20, 30, 50, .06);
    overflow: hidden;
}

.card-header {
    background: #fff;
    padding: 17px 20px;
    font-weight: 700;
    border-bottom: 1px solid #edf0f4;
}

.card-body {
    background: #fff;
}

.form-label {
    font-weight: 600;
    font-size: 14px;
}

.form-control {
    border-radius: 8px;
    padding: 10px 12px;
}

.form-control:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 .2rem rgba(197, 157, 95, .15);
}

.btn-gold {
    color: #fff;
    background: var(--gold-dark);
    border-color: var(--gold-dark);
}

.btn-gold:hover {
    color: #fff;
    background: #805d2d;
}

.about-thumb {
    width: 85px;
    height: 65px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f8f9fa;
}

.current-image {
    width: 180px;
    max-width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #ddd;
}

.table thead th {
    white-space: nowrap;
    font-size: 13px;
    color: #4a5568;
}

.table td {
    vertical-align: middle;
}

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

@media (max-width: 576px) {
    .page-header {
        padding: 18px;
    }

    .page-header h2 {
        font-size: 22px;
    }

    .card-header {
        padding: 14px;
    }
}
</style>
</head>

<body>

<div class="container-fluid p-3 p-md-4">

    <!-- PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="mb-2">
                <i class="bi bi-info-circle me-2"></i>Manage About
            </h2>
            <p class="mb-0">Royal Marriage Hall — About section management</p>
        </div>

        <a href="dashboard.php" class="btn btn-light">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>

    <!-- ALERT MESSAGE -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div class="card mb-4" id="aboutForm">

        <div class="card-header">
            <i class="bi bi-pencil-square me-2"></i>
            <?= $editing ? 'Edit About Section' : 'Add New About Section' ?>
        </div>

        <div class="card-body p-3 p-md-4">

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['about_csrf']) ?>">

                <input type="hidden" name="action" value="save">

                <input type="hidden" name="id"
                       value="<?= e($editing['id'] ?? 0) ?>">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Section Label *</label>
                        <input type="text" name="section_label"
                               class="form-control" required
                               value="<?= e($editing['section_label'] ?? 'WELCOME TO OUR VENUE') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Main Heading *</label>
                        <input type="text" name="heading"
                               class="form-control" required
                               value="<?= e($editing['heading'] ?? 'Where Your Forever Begins') ?>">
                    </div>

                    <!-- IMAGE UPLOAD -->
                    <div class="col-md-6">
                        <label class="form-label">Upload Image</label>

                        <input type="file" name="image_file"
                               class="form-control"
                               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                        <small class="text-muted">
                            JPG, PNG or WEBP; maximum 5 MB.
                        </small>

                        <?php if (!empty($editing['image'])): ?>
                            <div class="mt-3">
                                <img src="<?= e($editing['image']) ?>"
                                     class="current-image"
                                     alt="<?= e($editing['image_alt'] ?? 'Current About image') ?>"
                                     onerror="this.style.display='none';">

                                <div class="small text-muted mt-1">
                                    Current image
                                </div>
                            </div>
                        <?php endif; ?>

                        <label class="form-label mt-3">Or Image URL (HTTPS)</label>

                        <input type="url" name="image_url"
                               class="form-control"
                               placeholder="https://example.com/image.jpg"
                               value="<?= e(
                                   !empty($editing['image']) &&
                                   filter_var($editing['image'], FILTER_VALIDATE_URL)
                                       ? $editing['image'] : ''
                               ) ?>">

                        <small class="text-muted">
                            Leave blank to keep the existing image when editing.
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Image Alt Text</label>

                        <input type="text" name="image_alt"
                               class="form-control"
                               value="<?= e($editing['image_alt'] ?? 'Beautiful marriage hall setting') ?>">

                        <label class="form-label mt-3">Stamp Text</label>

                        <input type="text" name="stamp_text"
                               class="form-control"
                               value="<?= e($editing['stamp_text'] ?? 'MADE WITH LOVE') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description 1 *</label>
                        <textarea name="description1" rows="4"
                                  class="form-control"
                                  required><?= e($editing['description1'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description 2 *</label>
                        <textarea name="description2" rows="4"
                                  class="form-control"
                                  required><?= e($editing['description2'] ?? '') ?></textarea>
                    </div>

                    <?php
                    $defaultFeatures = [
                        1 => 'Elegant Ambience',
                        2 => 'Special Occasions',
                        3 => 'Flexible Arrangements',
                        4 => 'Memorable Moments'
                    ];
                    ?>

                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="col-md-6">
                            <label class="form-label">
                                Feature <?= $i ?> *
                            </label>

                            <input type="text" name="feature<?= $i ?>"
                                   class="form-control" required
                                   value="<?= e(
                                       $editing["feature$i"] ?? $defaultFeatures[$i]
                                   ) ?>">
                        </div>
                    <?php endfor; ?>

                    <div class="col-md-6">
                        <label class="form-label">Button Text</label>
                        <input type="text" name="button_text"
                               class="form-control"
                               value="<?= e($editing['button_text'] ?? 'ENQUIRE ABOUT YOUR EVENT') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Button Link</label>
                        <input type="text" name="button_link"
                               class="form-control"
                               placeholder="#booking"
                               value="<?= e($editing['button_link'] ?? '#booking') ?>">
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2 mt-4">

                        <button type="submit" class="btn btn-gold px-4">
                            <i class="bi bi-save me-1"></i>
                            <?= $editing ? 'Update About' : 'Save About' ?>
                        </button>

                        <?php if ($editing): ?>
                            <a href="manage_about.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel Edit
                            </a>
                        <?php else: ?>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                            </button>
                        <?php endif; ?>

                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- RECORD LIST -->
    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <span><i class="bi bi-table me-2"></i>About Records</span>
            <span class="badge text-bg-dark">
                <?= $result->num_rows ?> Records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Heading / Description</th>
                            <th>Section Label</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($result->num_rows === 0): ?>

                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No About records found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>
                                <td><?= (int)$row['id'] ?></td>

                                <td>
                                    <?php if (!empty($row['image'])): ?>
                                        <img src="<?= e($row['image']) ?>"
                                             class="about-thumb"
                                             alt="<?= e($row['image_alt'] ?? 'About image') ?>"
                                             loading="lazy"
                                             onerror="this.style.display='none';">
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <strong><?= e($row['heading']) ?></strong>

                                    <div class="small text-muted mt-1">
                                        <?= e(mb_strimwidth(
                                            $row['description1'] ?? '',
                                            0,
                                            85,
                                            '...'
                                        )) ?>
                                    </div>
                                </td>

                                <td><?= e($row['section_label']) ?></td>

                                <td>
                                    <?php if ((int)$row['status'] === 1): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="action-buttons">

                                        <!-- EDIT -->
                                        <a href="manage_about.php?edit=<?= (int)$row['id'] ?>#aboutForm"
                                           class="btn btn-sm btn-primary"
                                           title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <!-- ACTIVATE / DEACTIVATE -->
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION['about_csrf']) ?>">

                                            <input type="hidden" name="action"
                                                   value="toggle">

                                            <input type="hidden" name="id"
                                                   value="<?= (int)$row['id'] ?>">

                                            <button type="submit"
                                                    class="btn btn-sm btn-warning"
                                                    title="Activate / Deactivate">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>

                                        <!-- DELETE -->
                                        <form method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this About record?');">

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION['about_csrf']) ?>">

                                            <input type="hidden" name="action"
                                                   value="delete">

                                            <input type="hidden" name="id"
                                                   value="<?= (int)$row['id'] ?>">

                                            <button type="submit"
                                                    class="btn btn-sm btn-danger"
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>