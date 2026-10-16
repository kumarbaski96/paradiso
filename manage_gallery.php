
<?php
session_start();
include 'config/db.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset('utf8mb4');

$uploadDir = '../uploads/gallery/';
$uploadUrl = '../uploads/gallery/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (empty($_SESSION['gallery_csrf'])) {
    $_SESSION['gallery_csrf'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($message, $type = 'success') {
    $_SESSION['gallery_message'] = $message;
    $_SESSION['gallery_message_type'] = $type;
    header('Location: manage_gallery.php');
    exit;
}

function validImageUrl($url) {
    if ($url === '') {
        return true;
    }

    return filter_var($url, FILTER_VALIDATE_URL)
        && in_array(
            strtolower((string)parse_url($url, PHP_URL_SCHEME)),
            ['http', 'https'],
            true
        );
}

function removeLocalGalleryImage($image, $uploadUrl, $uploadDir) {
    // Delete only files stored in our gallery upload directory.
    if (
        strpos($image, $uploadUrl) === 0 &&
        basename($image) === substr($image, strlen($uploadUrl))
    ) {
        $path = $uploadDir . basename($image);

        if (is_file($path)) {
            unlink($path);
        }
    }
}

function uploadGalleryImage($field, $uploadDir, $uploadUrl) {
    if (
        !isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must be 5 MB or smaller.');
    }

    $tmp = $_FILES[$field]['tmp_name'];

    $imageInfo = getimagesize($tmp);

    if ($imageInfo === false) {
        throw new RuntimeException('Please upload a valid image.');
    }

    $mime = $imageInfo['mime'];

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException(
            'Only JPG, PNG, WEBP and GIF images are allowed.'
        );
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($tmp, $uploadDir . $filename)) {
        throw new RuntimeException('Unable to save the uploaded image.');
    }

    return $uploadUrl . $filename;
}

$message = $_SESSION['gallery_message'] ?? '';
$messageType = $_SESSION['gallery_message_type'] ?? 'success';

unset($_SESSION['gallery_message'], $_SESSION['gallery_message_type']);

$editRecord = null;

// Process add, update, status and delete requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadedImage = null;

    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['gallery_csrf'],
                (string)$_POST['csrf_token']
            )
        ) {
            throw new RuntimeException(
                'Invalid request token. Please refresh the page.'
            );
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = filter_var(
                $_POST['id'] ?? 0,
                FILTER_VALIDATE_INT
            );

            $id = ($id && $id > 0) ? $id : 0;

            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $imageUrl = trim($_POST['image_url'] ?? '');
            $status = isset($_POST['status']) &&
                      $_POST['status'] === '1' ? 1 : 0;

            $sortOrder = filter_var(
                $_POST['sort_order'] ?? 0,
                FILTER_VALIDATE_INT
            );

            if ($sortOrder === false || $sortOrder < 0) {
                throw new RuntimeException(
                    'Sort order must be a non-negative integer.'
                );
            }

            if ($title === '' || $category === '') {
                throw new RuntimeException(
                    'Title and category are required.'
                );
            }

            if (mb_strlen($title) > 150 ||
                mb_strlen($category) > 100) {
                throw new RuntimeException(
                    'Title or category exceeds the allowed length.'
                );
            }

            if (!validImageUrl($imageUrl)) {
                throw new RuntimeException(
                    'Enter a valid HTTP or HTTPS image URL.'
                );
            }

            $oldImage = '';

            if ($id > 0) {
                $stmt = $conn->prepare(
                    'SELECT image FROM gallery WHERE id = ?'
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();

                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existing) {
                    throw new RuntimeException(
                        'Gallery record not found.'
                    );
                }

                $oldImage = $existing['image'];
            }

            $uploadedImage = uploadGalleryImage(
                'image_file',
                $uploadDir,
                $uploadUrl
            );

            if ($uploadedImage !== null) {
                $image = $uploadedImage;
            } elseif ($imageUrl !== '') {
                $image = $imageUrl;
            } elseif ($id > 0) {
                $image = $oldImage;
            } else {
                throw new RuntimeException(
                    'Please provide an image URL or upload an image.'
                );
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE gallery
                     SET title = ?, category = ?, image = ?,
                         status = ?, sort_order = ?
                     WHERE id = ?'
                );

                $stmt->bind_param(
                    'sssiii',
                    $title,
                    $category,
                    $image,
                    $status,
                    $sortOrder,
                    $id
                );

                $stmt->execute();
                $stmt->close();

                if ($uploadedImage !== null) {
                    removeLocalGalleryImage(
                        $oldImage,
                        $uploadUrl,
                        $uploadDir
                    );
                }

                redirectWithMessage('Gallery updated successfully.');
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO gallery
                     (title, category, image, status, sort_order)
                     VALUES (?, ?, ?, ?, ?)'
                );

                $stmt->bind_param(
                    'sssii',
                    $title,
                    $category,
                    $image,
                    $status,
                    $sortOrder
                );

                $stmt->execute();
                $stmt->close();

                redirectWithMessage('Gallery image added successfully.');
            }
        }

        if ($action === 'toggle_status') {
            $id = filter_var(
                $_POST['id'] ?? 0,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid gallery ID.');
            }

            $stmt = $conn->prepare(
                'UPDATE gallery SET status = IF(status = 1, 0, 1)
                 WHERE id = ?'
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows < 1) {
                $stmt->close();

                $check = $conn->prepare(
                    'SELECT id FROM gallery WHERE id = ?'
                );
                $check->bind_param('i', $id);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$exists) {
                    throw new RuntimeException(
                        'Gallery record not found.'
                    );
                }
            } else {
                $stmt->close();
            }

            redirectWithMessage('Gallery status updated.');
        }

        if ($action === 'delete') {
            $id = filter_var(
                $_POST['id'] ?? 0,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid gallery ID.');
            }

            $stmt = $conn->prepare(
                'SELECT image FROM gallery WHERE id = ?'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $record = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$record) {
                throw new RuntimeException(
                    'Gallery record not found.'
                );
            }

            $stmt = $conn->prepare(
                'DELETE FROM gallery WHERE id = ?'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            removeLocalGalleryImage(
                $record['image'],
                $uploadUrl,
                $uploadDir
            );

            redirectWithMessage('Gallery image deleted successfully.');
        }

        throw new RuntimeException('Invalid action.');

    } catch (Throwable $ex) {
        if ($uploadedImage !== null &&
            is_file($uploadDir . basename($uploadedImage))) {
            unlink($uploadDir . basename($uploadedImage));
        }

        redirectWithMessage($ex->getMessage(), 'danger');
    }
}

// Load record for editing.
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {
        $stmt = $conn->prepare(
            'SELECT * FROM gallery WHERE id = ?'
        );
        $stmt->bind_param('i', $editId);
        $stmt->execute();

        $editRecord = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$editRecord) {
            $message = 'Gallery record not found.';
            $messageType = 'danger';
        }
    }
}

// Fetch all records for the admin table.
$result = $conn->query(
    'SELECT * FROM gallery ORDER BY sort_order ASC, id DESC'
);

$totalImages = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM gallery'
)->fetch_assoc()['total'];

$activeImages = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM gallery WHERE status = 1'
)->fetch_assoc()['total'];

$inactiveImages = $totalImages - $activeImages;

$isEditing = $editRecord !== null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Gallery | Admin Panel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f5f6fa;
            color: #292d32;
        }

        .page-header {
            background: #fff;
            padding: 24px;
            border-radius: 14px;
            box-shadow: 0 3px 15px #00000008;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eee;
            height: 100%;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
        }

        .panel-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            border: 1px solid #eee;
            margin-bottom: 24px;
        }

        .gallery-thumb {
            width: 85px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
            background: #eee;
        }

        .form-label {
            font-weight: 600;
        }

        .table th {
            font-size: 13px;
            white-space: nowrap;
            background: #f8f9fc;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-gold {
            background: #ad8745;
            border-color: #ad8745;
            color: white;
        }

        .btn-gold:hover {
            background: #8f6d33;
            color: white;
        }

        .preview-image {
            max-width: 100%;
            max-height: 180px;
            object-fit: contain;
            border-radius: 8px;
        }

        @media (max-width: 576px) {
            .panel-card, .page-header {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid py-4 px-3 px-lg-4">
    

    <div class="page-header d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>
            <h2 class="h4 mb-1">
                <i class="fa-solid fa-images text-warning me-2"></i>
                Manage Gallery
            </h2>
            <p class="text-secondary mb-0">
                Add, edit, sort and manage website gallery images.
            </p>
        </div>

        <a href="#galleryForm" class="btn btn-gold">
            <i class="fa-solid fa-plus me-1"></i> Add New Image
        </a>
         <a href="dashboard.php" class="btn btn-gold">
            <i class="fa-solid fa-plus me-1"></i> Back to Dashboard
        </a>

    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show">
            <?= e($message) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="text-secondary">Total Images</div>
                <div class="stat-number"><?= $totalImages ?></div>
                <i class="fa-solid fa-images text-warning"></i>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="text-secondary">Active Images</div>
                <div class="stat-number text-success"><?= $activeImages ?></div>
                <i class="fa-solid fa-eye text-success"></i>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="text-secondary">Inactive Images</div>
                <div class="stat-number text-danger"><?= $inactiveImages ?></div>
                <i class="fa-solid fa-eye-slash text-danger"></i>
            </div>
        </div>

    </div>

    <!-- Add / Edit Form -->
    <div class="panel-card" id="galleryForm">

        <h3 class="h5 mb-4">
            <i class="fa-solid fa-pen-to-square me-2 text-warning"></i>
            <?= $isEditing ? 'Edit Gallery Image' : 'Add New Gallery Image' ?>
        </h3>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['gallery_csrf']) ?>">

            <input type="hidden" name="action" value="save">

            <input type="hidden" name="id"
                   value="<?= e($editRecord['id'] ?? 0) ?>">

            <div class="row g-3">

                <div class="col-12 col-md-6">
                    <label class="form-label">Image Title *</label>
                    <input type="text" name="title" class="form-control"
                           maxlength="150" required
                           placeholder="e.g. Wedding Ceremony"
                           value="<?= e($editRecord['title'] ?? '') ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Category *</label>
                    <input type="text" name="category" class="form-control"
                           maxlength="100" required
                           placeholder="e.g. WEDDING"
                           value="<?= e($editRecord['category'] ?? '') ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Image URL</label>
                    <input type="url" name="image_url" id="imageUrl"
                           class="form-control"
                           placeholder="https://example.com/image.jpg"
                           value="<?php
                               $currentImage = $editRecord['image'] ?? '';
                               echo e(
                                   preg_match('~^https?://~i', $currentImage)
                                       ? $currentImage
                                       : ''
                               );
                           ?>">

                    <small class="text-secondary">
                        Enter a URL, or upload a local image below.
                    </small>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Upload Image</label>
                    <input type="file" name="image_file" id="imageFile"
                           class="form-control"
                           accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">

                    <small class="text-secondary">
                        JPG, PNG, WEBP or GIF; maximum 5 MB.
                        A new upload takes priority over the URL.
                    </small>
                </div>

                <?php if ($isEditing): ?>
                    <div class="col-12">
                        <label class="form-label">Current Image</label><br>
                        <img src="<?= e($editRecord['image']) ?>"
                             alt="Current gallery image"
                             class="preview-image"
                             onerror="this.style.display='none'">
                    </div>
                <?php endif; ?>

                <div class="col-12 col-md-4">
                    <label class="form-label">Sort Order *</label>
                    <input type="number" name="sort_order"
                           class="form-control" min="0" required
                           value="<?= e($editRecord['sort_order'] ?? 0) ?>">
                    <small class="text-secondary">
                        Smaller numbers appear first.
                    </small>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="1"
                            <?= (string)($editRecord['status'] ?? '1') === '1'
                                ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="0"
                            <?= (string)($editRecord['status'] ?? '1') === '0'
                                ? 'selected' : '' ?>>
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-gold">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        <?= $isEditing ? 'Update Image' : 'Save Image' ?>
                    </button>

                    <?php if ($isEditing): ?>
                        <a href="manage_gallery.php"
                           class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    <?php else: ?>
                        <button type="reset"
                                class="btn btn-outline-secondary">
                            Reset
                        </button>
                    <?php endif; ?>
                </div>

            </div>
        </form>

    </div>

    <!-- Gallery Records -->
    <div class="panel-card">

        <div class="d-flex flex-wrap justify-content-between
                    align-items-center gap-3 mb-3">

            <h3 class="h5 mb-0">All Gallery Images</h3>

            <input type="search" id="tableSearch"
                   class="form-control"
                   style="max-width:300px"
                   placeholder="Search title or category...">
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle" id="galleryTable">

                <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Title / Category</th>
                    <th>Sort Order</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>
                            <td><?= (int)$row['id'] ?></td>

                            <td>
                                <img
                                    src="<?= e($row['image']) ?>"
                                    class="gallery-thumb"
                                    alt="<?= e($row['title']) ?>"
                                    loading="lazy"
                                    onerror="this.style.visibility='hidden'">
                            </td>

                            <td>
                                <strong><?= e($row['title']) ?></strong><br>
                                <small class="text-secondary">
                                    <?= e($row['category']) ?>
                                </small>
                            </td>

                            <td>
                                <span class="badge text-bg-light border">
                                    <?= (int)$row['sort_order'] ?>
                                </span>
                            </td>

                            <td>
                                <?php if ((int)$row['status'] === 1): ?>
                                    <span class="badge text-bg-success">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e($row['created_at'] ?? '') ?>
                            </td>

                            <td>
                                <div class="d-flex gap-1">

                                    <a href="?edit=<?= (int)$row['id'] ?>#galleryForm"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['gallery_csrf']) ?>">

                                        <input type="hidden" name="action"
                                               value="toggle_status">

                                        <input type="hidden" name="id"
                                               value="<?= (int)$row['id'] ?>">

                                        <button type="submit"
                                                class="btn btn-sm btn-outline-warning"
                                                title="Change status">
                                            <i class="fa-solid fa-power-off"></i>
                                        </button>
                                    </form>

                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this gallery image permanently?');">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['gallery_csrf']) ?>">

                                        <input type="hidden" name="action"
                                               value="delete">

                                        <input type="hidden" name="id"
                                               value="<?= (int)$row['id'] ?>">

                                        <button type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>

                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center py-4">
                            No gallery records found. Add your first image above.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Search gallery records by title, category or ID.
document.getElementById('tableSearch').addEventListener('input', function () {
    const searchText = this.value.toLowerCase();

    document.querySelectorAll('#galleryTable tbody tr').forEach(function (row) {
        row.style.display = row.innerText.toLowerCase().includes(searchText)
            ? ''
            : 'none';
    });
});

// Show a preview when a local image is selected.
document.getElementById('imageFile').addEventListener('change', function () {
    const file = this.files[0];

    if (file && file.size > 5 * 1024 * 1024) {
        alert('Please select an image of 5 MB or smaller.');
        this.value = '';
    }
});
</script>

</body>
</html>
