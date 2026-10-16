
<?php
session_start();
require_once __DIR__ . '/config/db.php';
include 'auth.php';
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database error: config/db.php must define a MySQLi connection named $conn.');
}

// Use the actual current PHP page URL instead of hard-coding a filename.
$self = strtok($_SERVER['REQUEST_URI'], '?');
$self = $self ?: $_SERVER['PHP_SELF'];

if (empty($_SESSION['banner_csrf'])) {
    $_SESSION['banner_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$notice = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectAdmin($message) {
    // Return to the same PHP file that processed the request.
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    if (!$url) {
        $url = $_SERVER['PHP_SELF'];
    }

    header('Location: ' . $url . '?msg=' . urlencode($message));
    exit;
}

function uploadBannerImage($file) {
    if (!$file || !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must be smaller than 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime]) || !getimagesize($file['tmp_name'])) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }

    $folder = __DIR__ . '/img/';

    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        throw new RuntimeException('Cannot create the img folder.');
    }

    $filename = 'banner_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $folder . $filename)) {
        throw new RuntimeException('Unable to save the uploaded image.');
    }

    return 'img/' . $filename;
}

function deleteUploadedImage($path) {
    // Only delete files created by this admin panel.
    // Original files such as b1.jpeg and b2.jpeg are preserved.
    if (preg_match('~^img/banner_[a-f0-9]{24}\.(jpg|png|webp)$~', (string)$path)) {
        $file = __DIR__ . '/' . $path;

        if (is_file($file)) {
            @unlink($file);
        }
    }
}

// --------------------------------------------------
// ADD, UPDATE, ACTIVATE, DEACTIVATE AND DELETE
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newImage = null;

    try {
        $sessionToken = $_SESSION['banner_csrf'] ?? '';
        $postedToken = $_POST['csrf'] ?? '';

        if (!is_string($postedToken) ||
            !hash_equals($sessionToken, $postedToken)) {
            throw new RuntimeException('Security token expired. Refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

        if ($id === false || $id < 0) {
            throw new RuntimeException('Invalid banner ID.');
        }

        // ADD OR UPDATE
        if ($action === 'save') {
            $title       = trim($_POST['banner_title'] ?? '');
            $subtitle    = trim($_POST['banner_subtitle'] ?? '');
            $description = trim($_POST['banner_description'] ?? '');
            $buttonText  = trim($_POST['button_text'] ?? '');
            $buttonLink  = trim($_POST['button_link'] ?? '');
            $sortOrder   = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);

            if (
                $title === '' || $subtitle === '' || $description === '' ||
                $buttonText === '' || $buttonLink === '' ||
                $sortOrder === false || $sortOrder === null || $sortOrder < 0 ||
                strlen($title) > 255 || strlen($subtitle) > 255 ||
                strlen($description) > 5000 || strlen($buttonText) > 100 ||
                strlen($buttonLink) > 255
            ) {
                throw new RuntimeException('Please complete all fields with valid values.');
            }

            // Permit only text and <br> line breaks in the title.
            $plainTitle = preg_replace('~<br\s*/?>~i', '', $title);

            if (strip_tags($plainTitle) !== $plainTitle) {
                throw new RuntimeException('Title can contain text and line breaks only.');
            }

            $title = preg_replace('~<br\s*/?>~i', '<br>', $title);

            // Accept anchor links, local absolute paths, and HTTP(S) URLs.
            $validLink =
                preg_match('~^#[a-zA-Z][a-zA-Z0-9_-]*$~', $buttonLink) ||
                preg_match('~^/(?!/)[^\s]*$~', $buttonLink) ||
                preg_match('~^https?://[^\s]+$~i', $buttonLink);

            if (!$validLink) {
                throw new RuntimeException('Use a valid link, such as #contact, /contact.php or an HTTP(S) URL.');
            }

            $oldImage = null;

            if ($id > 0) {
                $stmt = $conn->prepare('SELECT banner_image FROM banners WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();

                if (!$old) {
                    throw new RuntimeException('Banner not found.');
                }

                $oldImage = $old['banner_image'];
            }

            $newImage = uploadBannerImage($_FILES['banner_image'] ?? null);

            if ($id > 0) {
                // UPDATE EXISTING BANNER
                $image = $newImage ?? $oldImage;

                $stmt = $conn->prepare(
                    'UPDATE banners SET
                        banner_title = ?,
                        banner_subtitle = ?,
                        banner_description = ?,
                        banner_image = ?,
                        button_text = ?,
                        button_link = ?,
                        sort_order = ?
                     WHERE id = ?'
                );

                $stmt->bind_param(
                    'ssssssii',
                    $title,
                    $subtitle,
                    $description,
                    $image,
                    $buttonText,
                    $buttonLink,
                    $sortOrder,
                    $id
                );

                $stmt->execute();

                if ($newImage !== null) {
                    deleteUploadedImage($oldImage);
                }

                redirectAdmin('updated');

            } else {
                // INSERT NEW BANNER
                if ($newImage === null) {
                    throw new RuntimeException('Please select an image for the new banner.');
                }

                $status = 1;

                $stmt = $conn->prepare(
                    'INSERT INTO banners
                    (banner_title, banner_subtitle, banner_description,
                     banner_image, button_text, button_link, sort_order, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->bind_param(
                    'ssssssii',
                    $title,
                    $subtitle,
                    $description,
                    $newImage,
                    $buttonText,
                    $buttonLink,
                    $sortOrder,
                    $status
                );

                $stmt->execute();

                redirectAdmin('added');
            }
        }

        // ACTIVATE OR DEACTIVATE
        elseif ($action === 'toggle') {
            if ($id <= 0) {
                throw new RuntimeException('Invalid banner ID.');
            }

            $stmt = $conn->prepare(
                'UPDATE banners SET status = IF(status = 1, 0, 1) WHERE id = ?'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $check = $conn->prepare('SELECT id FROM banners WHERE id = ?');
                $check->bind_param('i', $id);
                $check->execute();

                if (!$check->get_result()->fetch_assoc()) {
                    throw new RuntimeException('Banner not found.');
                }
            }

            redirectAdmin('status');
        }

        // DELETE
        elseif ($action === 'delete') {
            if ($id <= 0) {
                throw new RuntimeException('Invalid banner ID.');
            }

            $stmt = $conn->prepare('SELECT banner_image FROM banners WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $banner = $stmt->get_result()->fetch_assoc();

            if (!$banner) {
                throw new RuntimeException('Banner not found.');
            }

            $stmt = $conn->prepare('DELETE FROM banners WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();

            deleteUploadedImage($banner['banner_image']);

            redirectAdmin('deleted');
        }

        else {
            throw new RuntimeException('Invalid action.');
        }

    } catch (Throwable $ex) {
        if ($newImage !== null) {
            deleteUploadedImage($newImage);
        }

        $error = $ex->getMessage();
    }
}

// --------------------------------------------------
// NOTIFICATIONS AND FORM DATA
// --------------------------------------------------
$messages = [
    'added'   => 'Banner added successfully.',
    'updated' => 'Banner updated successfully.',
    'deleted' => 'Banner deleted successfully.',
    'status'  => 'Banner status changed successfully.'
];

$notice = $messages[$_GET['msg'] ?? ''] ?? '';

$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT);
$editId = ($editId !== false && $editId > 0) ? $editId : 0;

$showForm = isset($_GET['add']) || $editId > 0;

if ($error !== '') {
    $showForm = true;
}

$editBanner = null;

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM banners WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editBanner = $stmt->get_result()->fetch_assoc();

    if (!$editBanner) {
        $error = 'Banner not found.';
        $showForm = false;
    }
}

$formData = $editBanner ?? [
    'id' => 0,
    'banner_title' => '',
    'banner_subtitle' => '',
    'banner_description' => '',
    'banner_image' => '',
    'button_text' => 'GET IN TOUCH',
    'button_link' => '#contact',
    'sort_order' => 0
];

if ($error !== '' && ($_POST['action'] ?? '') === 'save') {
    foreach ([
        'banner_title', 'banner_subtitle', 'banner_description',
        'button_text', 'button_link', 'sort_order'
    ] as $field) {
        $formData[$field] = $_POST[$field] ?? $formData[$field];
    }

    $formData['id'] = (int)($_POST['id'] ?? 0);
}

$result = $conn->query('SELECT * FROM banners ORDER BY sort_order ASC, id DESC');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Banner Management | Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .admin-header {
            background: #202b3c;
            color: white;
            padding: 22px 0;
        }

        .panel {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 3px 14px #0000000b;
            margin-bottom: 25px;
        }

        .banner-thumb {
            width: 110px;
            height: 68px;
            object-fit: cover;
            border-radius: 6px;
        }

        .table td, .table th {
            vertical-align: middle;
        }

        .form-label {
            font-weight: 600;
        }

        .action-form {
            display: inline-block;
            margin: 2px;
        }

        @media (max-width: 768px) {
            .panel { padding: 15px; }
            .banner-thumb { width: 80px; height: 55px; }
        }
    </style>
</head>

<body>

<header class="admin-header">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Banner Management</h2>
            <p class="mb-0">Manage your website carousel banners</p>
        </div>

        <!-- <a href="<?= e(dirname($self)) ?>/" class="btn btn-outline-light">
            Back to Website
        </a> -->
          <a href="dashboard.php" class="btn btn-outline-light">
            Back to dashboard
        </a>
    </div>
</header>

<div class="container py-4">

    <?php if ($notice !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($notice) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h4 class="mb-0">Manage Banners</h4>

        <div class="d-flex gap-2">
            <a href="<?= e($self) ?>" class="btn btn-outline-secondary">View All</a>
            <a href="<?= e($self) ?>?add=1" class="btn btn-success">
                <span class="fs-5">+</span> Add New Banner
            </a>
        </div>
    </div>

    <?php if ($showForm): ?>
    <div class="panel" id="bannerForm">
        <h4 class="mb-4">
            <?= (int)$formData['id'] > 0 ? 'Edit Banner' : 'Add New Banner' ?>
        </h4>

        <form action="<?= e($self) ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['banner_csrf']) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$formData['id'] ?>">

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Banner Title</label>
                    <textarea name="banner_title" class="form-control" rows="2" maxlength="255" required><?= e($formData['banner_title']) ?></textarea>
                    <small class="text-muted">Example: The Celebration&lt;br&gt;Begins Here</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Subtitle</label>
                    <input type="text" name="banner_subtitle" class="form-control" maxlength="255" required value="<?= e($formData['banner_subtitle']) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="banner_description" class="form-control" rows="3" maxlength="5000" required><?= e($formData['banner_description']) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Banner Image</label>
                    <input type="file" name="banner_image" class="form-control" accept=".jpg,.jpeg,.png,.webp" <?= (int)$formData['id'] === 0 ? 'required' : '' ?>>
                    <small class="text-muted">JPG, PNG or WEBP. Maximum 5 MB. Leave empty to keep the current image.</small>

                    <?php if (!empty($formData['banner_image'])): ?>
                        <div class="mt-3">
                            <div class="small text-muted mb-1">Current image</div>
                            <img src="<?= e($formData['banner_image']) ?>" class="banner-thumb" alt="Current banner">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Button Text</label>
                    <input type="text" name="button_text" class="form-control" maxlength="100" required value="<?= e($formData['button_text']) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Button Link</label>
                    <input type="text" name="button_link" class="form-control" maxlength="255" required value="<?= e($formData['button_link']) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" min="0" required value="<?= e($formData['sort_order']) ?>">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?= (int)$formData['id'] > 0 ? 'Update Banner' : 'Save Banner' ?>
                    </button>

                    <a href="<?= e($self) ?>" class="btn btn-secondary">Cancel</a>
                </div>

            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="panel">
        <h4 class="mb-3">All Banners</h4>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Subtitle</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th style="min-width:260px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>

                        <td>
                            <img src="<?= e($row['banner_image']) ?>" class="banner-thumb" alt="Banner image">
                        </td>

                        <td><?= e(strip_tags($row['banner_title'])) ?></td>
                        <td><?= e($row['banner_subtitle']) ?></td>
                        <td><?= (int)$row['sort_order'] ?></td>

                        <td>
                            <?php if ((int)$row['status'] === 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <!-- EDIT -->
                            <a href="<?= e($self) ?>?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary action-form">
                                Edit
                            </a>

                            <!-- ACTIVATE / DEACTIVATE -->
                            <form action="<?= e($self) ?>" method="POST" class="action-form">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['banner_csrf']) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                                <button type="submit" class="btn btn-sm btn-warning">
                                    <?= (int)$row['status'] === 1 ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>

                            <!-- DELETE -->
                            <form action="<?= e($self) ?>" method="POST" class="action-form" onsubmit="return confirm('Are you sure you want to delete this banner?');">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['banner_csrf']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            No banners found. Click Add New Banner to begin.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
