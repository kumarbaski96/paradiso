
<?php
session_start();

include 'config/db.php';

// Protect admin page
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = 'success';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// Default form values
$editData = [
    'id' => '',
    'platform_name' => '',
    'social_url' => '',
    'icon_class' => '',
    'sort_order' => 0,
    'status' => 1
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid request. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    // ADD OR UPDATE
    if ($action === 'save') {

        $id = (int)($_POST['id'] ?? 0);
        $platformName = trim($_POST['platform_name'] ?? '');
        $socialUrl = trim($_POST['social_url'] ?? '');
        $iconClass = trim($_POST['icon_class'] ?? '');
        $sortOrder = filter_var(
            $_POST['sort_order'] ?? 0,
            FILTER_VALIDATE_INT
        );
        $status = isset($_POST['status']) ? 1 : 0;

        $scheme = strtolower(parse_url($socialUrl, PHP_URL_SCHEME) ?? '');

        if (
            $platformName === '' ||
            $socialUrl === '' ||
            $iconClass === '' ||
            $sortOrder === false ||
            $sortOrder < 0
        ) {
            $message = 'Please enter all required details and a valid sort order.';
            $messageType = 'danger';

        } elseif (
            !filter_var($socialUrl, FILTER_VALIDATE_URL) ||
            !in_array($scheme, ['http', 'https'], true)
        ) {
            $message = 'Please enter a valid HTTP or HTTPS URL.';
            $messageType = 'danger';

        } else {

            if ($id > 0) {

                $stmt = $conn->prepare(
                    "UPDATE social_networking
                     SET platform_name = ?,
                         social_url = ?,
                         icon_class = ?,
                         sort_order = ?,
                         status = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "sssiii",
                    $platformName,
                    $socialUrl,
                    $iconClass,
                    $sortOrder,
                    $status,
                    $id
                );

                $successMessage = 'Social media record updated successfully.';

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO social_networking
                     (platform_name, social_url, icon_class, sort_order, status)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssii",
                    $platformName,
                    $socialUrl,
                    $iconClass,
                    $sortOrder,
                    $status
                );

                $successMessage = 'Social media record added successfully.';
            }

            if ($stmt->execute()) {
                $stmt->close();

                header('Location: manage_social.php?message=' .
                    urlencode($successMessage));
                exit;

            } else {
                $message = 'Unable to save the record.';
                $messageType = 'danger';
                $stmt->close();
            }
        }

        // Preserve entered values if validation fails
        $editData = [
            'id' => $id,
            'platform_name' => $platformName,
            'social_url' => $socialUrl,
            'icon_class' => $iconClass,
            'sort_order' => $sortOrder === false ? 0 : $sortOrder,
            'status' => $status
        ];
    }

    // DELETE
    elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare(
                "DELETE FROM social_networking WHERE id = ?"
            );
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'Social media record deleted successfully.';
            } else {
                $message = 'Record not found or could not be deleted.';
                $messageType = 'danger';
            }

            $stmt->close();
        }
    }

    // ACTIVATE / DEACTIVATE
    elseif ($action === 'toggle_status') {

        $id = (int)($_POST['id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0);

        if ($id > 0 && in_array($newStatus, [0, 1], true)) {
            $stmt = $conn->prepare(
                "UPDATE social_networking SET status = ? WHERE id = ?"
            );
            $stmt->bind_param("ii", $newStatus, $id);

            if ($stmt->execute()) {
                $message = 'Status updated successfully.';
            } else {
                $message = 'Unable to update status.';
                $messageType = 'danger';
            }

            $stmt->close();
        }
    }
}

// Success message after redirect
if (isset($_GET['message'])) {
    $message = trim($_GET['message']);
    $messageType = 'success';
}

// Load record for editing
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM social_networking WHERE id = ?"
    );
    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $resultEdit = $stmt->get_result();

    if ($row = $resultEdit->fetch_assoc()) {
        $editData = $row;
    } else {
        $message = 'Record not found.';
        $messageType = 'danger';
    }

    $stmt->close();
}

// Fetch all records
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $searchLike = '%' . $search . '%';

    $stmt = $conn->prepare(
        "SELECT * FROM social_networking
         WHERE platform_name LIKE ?
            OR social_url LIKE ?
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->bind_param("ss", $searchLike, $searchLike);
    $stmt->execute();
    $socialResult = $stmt->get_result();

} else {
    $socialResult = $conn->query(
        "SELECT * FROM social_networking
         ORDER BY sort_order ASC, id ASC"
    );
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Social Media | Admin Panel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .page-header {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .panel {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
            margin-bottom: 24px;
        }

        .panel-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control, .form-select {
            padding: 10px 12px;
        }

        .btn-gold {
            background: #c49a35;
            color: #fff;
            border: none;
        }

        .btn-gold:hover {
            background: #a98020;
            color: #fff;
        }

        .social-preview {
            font-size: 25px;
            color: #b58b2b;
        }

        .table th {
            white-space: nowrap;
            background: #f8f9fa;
        }

        .url-cell {
            max-width: 260px;
            overflow-wrap: anywhere;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        @media (max-width: 767px) {
            .panel {
                padding: 16px;
            }

            .page-header {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid py-4 px-3 px-lg-4">

    <div class="page-header d-flex flex-wrap justify-content-between
                align-items-center gap-3">
        <div>
            <h2 class="mb-1">
                <i class="bi bi-share-fill text-warning"></i>
                Manage Social Networking
            </h2>
            <p class="text-muted mb-0">
                Add, edit, arrange and manage your social media links.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= h($messageType) ?> alert-dismissible fade show">
            <?= h($message) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div class="panel">
        <h3 class="panel-title">
            <i class="bi bi-<?= !empty($editData['id']) ? 'pencil-square' : 'plus-circle' ?>"></i>
            <?= !empty($editData['id']) ? 'Edit Social Media' : 'Add Social Media' ?>
        </h3>

        <form method="POST" action="manage_social.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h($editData['id']) ?>">

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Platform Name *</label>
                    <input type="text" name="platform_name"
                           class="form-control"
                           placeholder="e.g. Facebook"
                           maxlength="50"
                           value="<?= h($editData['platform_name']) ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Social Media URL *</label>
                    <input type="url" name="social_url"
                           class="form-control"
                           placeholder="https://example.com/your-profile"
                           maxlength="500"
                           value="<?= h($editData['social_url']) ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Bootstrap Icon Class *</label>
                    <input type="text" name="icon_class"
                           class="form-control"
                           placeholder="bi bi-facebook"
                           maxlength="100"
                           value="<?= h($editData['icon_class']) ?>"
                           required>
                    <small class="text-muted">
                        Example: bi bi-facebook, bi bi-whatsapp, bi bi-instagram
                    </small>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Sort Order *</label>
                    <input type="number" name="sort_order"
                           class="form-control"
                           min="0" max="2147483647"
                           value="<?= h($editData['sort_order']) ?>"
                           required>
                    <small class="text-muted">
                        Smaller number appears first.
                    </small>
                </div>

                <div class="col-md-3">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox"
                               name="status" value="1" id="status"
                               <?= (int)$editData['status'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status">
                            Active
                        </label>
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-gold">
                        <i class="bi bi-save"></i>
                        <?= !empty($editData['id']) ? 'Update Social Media' : 'Save Social Media' ?>
                    </button>

                    <?php if (!empty($editData['id'])): ?>
                        <a href="manage_social.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel Edit
                        </a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    <?php endif; ?>
                </div>

            </div>
        </form>
    </div>

    <!-- SOCIAL MEDIA LIST -->
    <div class="panel">
        <div class="d-flex flex-wrap justify-content-between
                    align-items-center gap-3 mb-4">
            <h3 class="panel-title mb-0">
                <i class="bi bi-list-ul"></i> Social Media Records
            </h3>

            <form method="GET" action="manage_social.php"
                  class="d-flex gap-2">
                <input type="search" name="search"
                       class="form-control"
                       placeholder="Search platform or URL"
                       value="<?= h($search) ?>">

                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-search"></i>
                </button>

                <?php if ($search !== ''): ?>
                    <a href="manage_social.php"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Icon</th>
                        <th>Platform</th>
                        <th>Social URL</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($socialResult && $socialResult->num_rows > 0): ?>

                    <?php while ($social = $socialResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$social['sort_order'] ?></td>

                            <td class="text-center">
                                <i class="<?= h($social['icon_class']) ?> social-preview"></i>
                            </td>

                            <td>
                                <strong><?= h($social['platform_name']) ?></strong>
                            </td>

                            <td class="url-cell">
                                <a href="<?= h($social['social_url']) ?>"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <?= h($social['social_url']) ?>
                                </a>
                            </td>

                            <td>
                                <?php if ((int)$social['status'] === 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <a href="manage_social.php?edit=<?= (int)$social['id'] ?>"
                                       class="btn btn-sm btn-primary"
                                       title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <form method="POST" action="manage_social.php"
                                          class="d-inline">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action"
                                               value="toggle_status">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$social['id'] ?>">
                                        <input type="hidden" name="new_status"
                                               value="<?= (int)$social['status'] === 1 ? 0 : 1 ?>">

                                        <button type="submit"
                                                class="btn btn-sm <?= (int)$social['status'] === 1 ? 'btn-warning' : 'btn-success' ?>"
                                                title="<?= (int)$social['status'] === 1 ? 'Deactivate' : 'Activate' ?>">
                                            <i class="bi <?= (int)$social['status'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="manage_social.php"
                                          class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this social media record?');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action"
                                               value="delete">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$social['id'] ?>">

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

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No social media records found.
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
