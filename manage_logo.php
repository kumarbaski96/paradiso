
<?php
session_start();

include 'config/db.php';

// Protect the admin page.
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Absolute server directory used to save uploaded files.
$uploadDir = __DIR__ . '/../uploads/logos/';

// Relative path stored in the database.
// This path is relative to the project root.
$uploadPath = 'uploads/logos/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        die('Unable to create the uploads/logos directory.');
    }
}

$message = '';
$error = '';
$editLogo = null;

// Escape HTML output.
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Build a browser URL for a database image path.
// Database paths should be relative to the project root.
function logoUrl($path)
{
    $path = trim((string)$path);

    if ($path === '') {
        return '../img/logo.jpeg';
    }

    // Prevent unsafe paths from being used as image URLs.
    if (
        strpos($path, '..') !== false ||
        strpos($path, '\\') !== false ||
        preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) ||
        strpos($path, '//') === 0
    ) {
        return '../img/logo.jpeg';
    }

    return '../' . ltrim($path, '/');
}

// Delete only files inside uploads/logos.
function deleteLogoFile($path)
{
    $path = ltrim((string)$path, '/');

    if (strpos($path, 'uploads/logos/') !== 0) {
        return;
    }

    $filename = basename($path);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $fullPath = __DIR__ . '/../uploads/logos/' . $filename;

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

// Process form submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        // ADD OR UPDATE LOGO
        if (isset($_POST['save_logo'])) {

            $id = (int)($_POST['id'] ?? 0);
            $textLogo = trim($_POST['text_logo'] ?? '');
            $link = trim($_POST['link'] ?? 'index.php');
            $status = isset($_POST['status']) ? 1 : 0;

            if ($textLogo === '') {
                throw new Exception('Please enter the logo alternative text.');
            }

            if ($link === '') {
                $link = 'index.php';
            }

            $existingLogo = '';

            // Fetch the old image path during editing.
            if ($id > 0) {
                $stmt = $conn->prepare(
                    "SELECT logo FROM logo WHERE id = ?"
                );

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existing) {
                    throw new Exception('Logo record not found.');
                }

                $existingLogo = $existing['logo'];
            }

            $logoPath = $existingLogo;
            $newFilePath = '';

            // Upload a new image if selected.
            if (
                isset($_FILES['logo_file']) &&
                $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Image upload failed.');
                }

                if ($_FILES['logo_file']['size'] > 3 * 1024 * 1024) {
                    throw new Exception('Maximum image size is 3 MB.');
                }

                $tmpFile = $_FILES['logo_file']['tmp_name'];
                $imageInfo = getimagesize($tmpFile);

                if ($imageInfo === false) {
                    throw new Exception('Please select a valid image.');
                }

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif'
                ];

                $mime = $imageInfo['mime'];

                if (!isset($allowedTypes[$mime])) {
                    throw new Exception(
                        'Only JPG, PNG, WEBP and GIF images are allowed.'
                    );
                }

                $filename = bin2hex(random_bytes(16))
                    . '.' . $allowedTypes[$mime];

                $destination = $uploadDir . $filename;

                if (!move_uploaded_file($tmpFile, $destination)) {
                    throw new Exception(
                        'Unable to save the uploaded image. Check folder permissions.'
                    );
                }

                $logoPath = $uploadPath . $filename;
                $newFilePath = $destination;
            }

            // A new record requires an image.
            if ($id === 0 && $logoPath === '') {
                throw new Exception('Please select a logo image.');
            }

            if ($id > 0) {

                $stmt = $conn->prepare(
                    "UPDATE logo
                     SET logo = ?, text_logo = ?, link = ?, status = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "sssii",
                    $logoPath,
                    $textLogo,
                    $link,
                    $status,
                    $id
                );

                if (!$stmt->execute()) {
                    $stmt->close();

                    if ($newFilePath !== '' && is_file($newFilePath)) {
                        unlink($newFilePath);
                    }

                    throw new Exception('Unable to update the logo.');
                }

                $stmt->close();

                // Delete the old uploaded file only after a successful update.
                if (
                    $logoPath !== $existingLogo &&
                    $existingLogo !== ''
                ) {
                    deleteLogoFile($existingLogo);
                }

                $message = 'Logo updated successfully.';

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO logo (logo, text_logo, link, status)
                     VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssi",
                    $logoPath,
                    $textLogo,
                    $link,
                    $status
                );

                if (!$stmt->execute()) {
                    $stmt->close();

                    if ($newFilePath !== '' && is_file($newFilePath)) {
                        unlink($newFilePath);
                    }

                    throw new Exception('Unable to save the logo.');
                }

                $stmt->close();

                $message = 'Logo added successfully.';
            }
        }

        // DELETE LOGO
        elseif (isset($_POST['delete_logo'])) {

            $id = (int)($_POST['id'] ?? 0);

            $stmt = $conn->prepare(
                "SELECT logo FROM logo WHERE id = ?"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $record = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$record) {
                throw new Exception('Logo record not found.');
            }

            $stmt = $conn->prepare(
                "DELETE FROM logo WHERE id = ?"
            );

            $stmt->bind_param("i", $id);

            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception('Unable to delete the logo.');
            }

            $stmt->close();

            deleteLogoFile($record['logo']);

            $message = 'Logo deleted successfully.';
        }

        // TOGGLE ACTIVE / INACTIVE STATUS
        elseif (isset($_POST['toggle_status'])) {

            $id = (int)($_POST['id'] ?? 0);

            $stmt = $conn->prepare(
                "UPDATE logo
                 SET status = IF(status = 1, 0, 1)
                 WHERE id = ?"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed < 0) {
                throw new Exception('Unable to change logo status.');
            }

            $check = $conn->prepare(
                "SELECT id FROM logo WHERE id = ?"
            );

            $check->bind_param("i", $id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$exists) {
                throw new Exception('Logo record not found.');
            }

            $message = 'Logo status updated successfully.';
        }

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

// Fetch a record for editing.
if (isset($_GET['edit'])) {

    $id = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM logo WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $editLogo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editLogo) {
        $error = 'Logo record not found.';
    }
}

// Fetch all logos.
$result = $conn->query(
    "SELECT * FROM logo ORDER BY id DESC"
);

if (!$result) {
    die('Database error: ' . e($conn->error));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Manage Logo | Paradiso The Venue</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <style>
        body {
            background: #f4f6fa;
            font-family: Arial, sans-serif;
        }

        .page-heading {
            color: #18243b;
            font-weight: 700;
        }

        .content-card {
            background: #fff;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 5px 24px rgba(0, 0, 0, .06);
        }

        .logo-preview {
            width: 125px;
            height: 70px;
            object-fit: contain;
            padding: 5px;
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 8px;
        }

        .table th {
            white-space: nowrap;
            background: #f8f9fc;
        }

        .form-label {
            font-weight: 600;
        }

        .btn-gold {
            background: #b58b42;
            color: #fff;
            border: none;
        }

        .btn-gold:hover {
            background: #967035;
            color: #fff;
        }

        @media (max-width: 576px) {
            .logo-preview {
                width: 90px;
                height: 55px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid px-3 px-md-4 py-4">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>
            <h2 class="page-heading mb-1">Manage Website Logo</h2>
            <p class="text-muted mb-0">
                Paradiso The Venue — Logo Management
            </p>
        </div>

        <a href="dashboard.php"
           target="_blank"
           class="btn btn-outline-dark">
            Back to Dashboard
        </a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($message) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($error) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div class="card content-card mb-4">

        <div class="card-header bg-white border-0 p-4">
            <h5 class="mb-0">
                <?= $editLogo ? 'Edit Logo' : 'Add New Logo' ?>
            </h5>
        </div>

        <div class="card-body px-4 pb-4">

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="id"
                       value="<?= e($editLogo['id'] ?? 0) ?>">

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">Logo Image</label>

                        <input
                            type="file"
                            name="logo_file"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp,.gif"
                            <?= $editLogo ? '' : 'required' ?>>

                        <small class="text-muted">
                            JPG, PNG, WEBP or GIF. Maximum 3 MB.
                            Leave empty when editing to keep the current image.
                        </small>

                        <?php if (!empty($editLogo['logo'])): ?>
                            <div class="mt-3">
                                <img
                                    src="<?= e(logoUrl($editLogo['logo'])) ?>"
                                    alt="<?= e($editLogo['text_logo']) ?>"
                                    class="logo-preview"
                                    onerror="this.onerror=null;this.alt='Image not found';">
                            </div>
                        <?php endif; ?>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Logo Alternative Text
                        </label>

                        <input
                            type="text"
                            name="text_logo"
                            class="form-control"
                            maxlength="150"
                            placeholder="Paradiso The Venue Logo"
                            value="<?= e($editLogo['text_logo'] ?? 'Paradiso The Venue Logo') ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">Logo Link</label>

                        <input
                            type="text"
                            name="link"
                            class="form-control"
                            placeholder="index.php"
                            value="<?= e($editLogo['link'] ?? 'index.php') ?>">

                    </div>

                    <div class="col-md-6">

                        <label class="form-label d-block">Status</label>

                        <div class="form-check form-switch mt-2">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="status"
                                id="status"
                                value="1"
                                <?= !isset($editLogo) ||
                                    (int)($editLogo['status'] ?? 1) === 1
                                    ? 'checked' : '' ?>>

                            <label class="form-check-label" for="status">
                                Active
                            </label>

                        </div>

                    </div>

                    <div class="col-12 d-flex gap-2 mt-4">

                        <button
                            type="submit"
                            name="save_logo"
                            class="btn btn-gold px-4">
                            <?= $editLogo ? 'Update Logo' : 'Save Logo' ?>
                        </button>

                        <?php if ($editLogo): ?>
                            <a href="manage_logo.php"
                               class="btn btn-secondary">
                                Cancel
                            </a>
                        <?php endif; ?>

                    </div>

                </div>

            </form>

        </div>
    </div>

    <!-- LOGO LIST -->
    <div class="card content-card">

        <div class="card-header bg-white border-0 p-4">
            <h5 class="mb-0">All Logo Records</h5>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Logo</th>
                            <th>Alternative Text</th>
                            <th>Link</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td class="ps-4">
                                    <?= (int)$row['id'] ?>
                                </td>

                                <td>
                                    <img
                                        src="<?= e(logoUrl($row['logo'])) ?>"
                                        alt="<?= e($row['text_logo']) ?>"
                                        class="logo-preview"
                                        onerror="this.onerror=null;this.alt='Image not found';">
                                </td>

                                <td><?= e($row['text_logo']) ?></td>

                                <td><?= e($row['link']) ?></td>

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

                                <td class="text-center">

                                    <div class="d-flex justify-content-center
                                                flex-wrap gap-2">

                                        <a
                                            href="?edit=<?= (int)$row['id'] ?>"
                                            class="btn btn-sm btn-primary">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              onsubmit="return confirm('Change this logo status?');">

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row['id'] ?>">

                                            <button
                                                type="submit"
                                                name="toggle_status"
                                                class="btn btn-sm btn-warning">
                                                Toggle
                                            </button>

                                        </form>

                                        <form method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this logo?');">

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row['id'] ?>">

                                            <button
                                                type="submit"
                                                name="delete_logo"
                                                class="btn btn-sm btn-danger">
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6"
                                class="text-center text-muted py-4">
                                No logo records found. Add your first logo above.
                            </td>
                        </tr>

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
