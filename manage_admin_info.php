
<?php
session_start();

require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = '';
$messageType = 'success';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$admin = [
    'id' => '',
    'name' => '',
    'Marriage_hall_name' => '',
    'username' => '',
    'email' => '',
    'address' => '',
    'district' => '',
    'state' => '',
    'pin_code' => '',
    'mobile1' => '',
    'mobile2' => '',
    'mobile3' => '',
    'email1' => '',
    'alt_email' => '',
    'status' => 1
];

// ADD / UPDATE / DELETE / STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($_SESSION['csrf_token'], $postedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page.');
    }

    $action = $_POST['action'] ?? '';

    // ADD OR UPDATE
    if ($action === 'save') {

        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $id = ($id !== false && $id > 0) ? $id : 0;

        $name = trim($_POST['name'] ?? '');
        $hallName = trim($_POST['Marriage_hall_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pinCode = trim($_POST['pin_code'] ?? '');
        $mobile1 = trim($_POST['mobile1'] ?? '');
        $mobile2 = trim($_POST['mobile2'] ?? '');
        $mobile3 = trim($_POST['mobile3'] ?? '');
        $email1 = trim($_POST['email1'] ?? '');
        $altEmail = trim($_POST['alt_email'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;

        $admin = [
            'id' => $id,
            'name' => $name,
            'Marriage_hall_name' => $hallName,
            'username' => $username,
            'email' => $email,
            'address' => $address,
            'district' => $district,
            'state' => $state,
            'pin_code' => $pinCode,
            'mobile1' => $mobile1,
            'mobile2' => $mobile2,
            'mobile3' => $mobile3,
            'email1' => $email1,
            'alt_email' => $altEmail,
            'status' => $status
        ];

        $errors = [];

        // Required fields
        if (
            $name === '' ||
            $hallName === '' ||
            $username === '' ||
            $address === '' ||
            $district === '' ||
            $state === '' ||
            $pinCode === ''
        ) {
            $errors[] = 'Please fill in all required admin and address fields.';
        }

        // Email validation only
        foreach ([
            'Primary email' => $email,
            'Contact email' => $email1,
            'Alternate email' => $altEmail
        ] as $label => $value) {

            if (
                $value !== '' &&
                !filter_var($value, FILTER_VALIDATE_EMAIL)
            ) {
                $errors[] = $label . ' is invalid.';
            }
        }

        // Text field length checks
        $lengthLimits = [
            'name' => [150, $name],
            'Marriage hall name' => [100, $hallName],
            'username' => [100, $username],
            'email' => [150, $email],
            'address' => [255, $address],
            'district' => [100, $district],
            'state' => [50, $state],
            'email1' => [100, $email1],
            'alt_email' => [100, $altEmail]
        ];

        foreach ($lengthLimits as $label => [$limit, $value]) {
            if (strlen($value) > $limit) {
                $errors[] = $label . ' exceeds its maximum length.';
            }
        }

        // PIN validation
        if ($pinCode !== '' && !preg_match('/^\d{1,7}$/', $pinCode)) {
            $errors[] = 'PIN code must contain 1 to 7 digits.';
        }

        // No mobile number validation.
        // Mobile fields accept any text and can be left blank.

        // Password required only for a new admin
        if ($id === 0 && trim($password) === '') {
            $errors[] = 'Password is required when adding an admin.';
        }

        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'Password must contain at least 8 characters.';
        }

        if (!empty($errors)) {

            $message = implode(' ', $errors);
            $messageType = 'danger';

        } else {

            try {

                // Check for duplicate username
                $check = $conn->prepare(
                    "SELECT id FROM admins
                     WHERE username = ? AND id <> ?
                     LIMIT 1"
                );

                $check->bind_param('si', $username, $id);
                $check->execute();

                $duplicate = $check->get_result()->num_rows > 0;
                $check->close();

                if ($duplicate) {

                    $message = 'This username is already in use.';
                    $messageType = 'danger';

                } else {

                    // UPDATE ADMIN
                    if ($id > 0) {

                        if ($password !== '') {

                            $passwordHash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $stmt = $conn->prepare(
                                "UPDATE admins SET
                                    name = ?,
                                    Marriage_hall_name = ?,
                                    username = ?,
                                    email = NULLIF(?, ''),
                                    password = ?,
                                    address = ?,
                                    district = ?,
                                    state = ?,
                                    pin_code = ?,
                                    mobile1 = ?,
                                    mobile2 = ?,
                                    mobile3 = ?,
                                    email1 = NULLIF(?, ''),
                                    alt_email = NULLIF(?, ''),
                                    status = ?
                                 WHERE id = ?"
                            );

                            $stmt->bind_param(
                                'ssssssssisssssii',
                                $name,
                                $hallName,
                                $username,
                                $email,
                                $passwordHash,
                                $address,
                                $district,
                                $state,
                                $pinCode,
                                $mobile1,
                                $mobile2,
                                $mobile3,
                                $email1,
                                $altEmail,
                                $status,
                                $id
                            );

                        } else {

                            $stmt = $conn->prepare(
                                "UPDATE admins SET
                                    name = ?,
                                    Marriage_hall_name = ?,
                                    username = ?,
                                    email = NULLIF(?, ''),
                                    address = ?,
                                    district = ?,
                                    state = ?,
                                    pin_code = ?,
                                    mobile1 = ?,
                                    mobile2 = ?,
                                    mobile3 = ?,
                                    email1 = NULLIF(?, ''),
                                    alt_email = NULLIF(?, ''),
                                    status = ?
                                 WHERE id = ?"
                            );

                            $stmt->bind_param(
                                'sssssssisssssii',
                                $name,
                                $hallName,
                                $username,
                                $email,
                                $address,
                                $district,
                                $state,
                                $pinCode,
                                $mobile1,
                                $mobile2,
                                $mobile3,
                                $email1,
                                $altEmail,
                                $status,
                                $id
                            );
                        }

                        $stmt->execute();
                        $stmt->close();

                        header(
                            'Location: manage_admin_info.php?message=' .
                            urlencode('Admin information updated successfully.')
                        );
                        exit;

                    } else {

                        // INSERT NEW ADMIN
                        $passwordHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $stmt = $conn->prepare(
                            "INSERT INTO admins (
                                name,
                                Marriage_hall_name,
                                username,
                                email,
                                password,
                                address,
                                district,
                                state,
                                pin_code,
                                mobile1,
                                mobile2,
                                mobile3,
                                email1,
                                alt_email,
                                status
                            ) VALUES (
                                ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?,
                                ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?
                            )"
                        );

                        $stmt->bind_param(
                            'ssssssssisssssi',
                            $name,
                            $hallName,
                            $username,
                            $email,
                            $passwordHash,
                            $address,
                            $district,
                            $state,
                            $pinCode,
                            $mobile1,
                            $mobile2,
                            $mobile3,
                            $email1,
                            $altEmail,
                            $status
                        );

                        $stmt->execute();
                        $stmt->close();

                        header(
                            'Location: manage_admin_info.php?message=' .
                            urlencode('Admin information added successfully.')
                        );
                        exit;
                    }
                }

            } catch (mysqli_sql_exception $e) {

                error_log('Admin CRUD error: ' . $e->getMessage());

                $message = 'Database operation failed. Please check your table structure and data.';
                $messageType = 'danger';
            }
        }
    }

    // ACTIVATE / DEACTIVATE
    elseif ($action === 'toggle_status') {

        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $newStatus = filter_var(
            $_POST['new_status'] ?? -1,
            FILTER_VALIDATE_INT
        );

        if (
            $id !== false && $id > 0 &&
            in_array($newStatus, [0, 1], true)
        ) {
            $stmt = $conn->prepare(
                "UPDATE admins SET status = ? WHERE id = ?"
            );

            $stmt->bind_param('ii', $newStatus, $id);
            $stmt->execute();
            $stmt->close();

            header(
                'Location: manage_admin_info.php?message=' .
                urlencode('Admin status updated successfully.')
            );
            exit;
        }
    }

    // DELETE ADMIN
    elseif ($action === 'delete') {

        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

        if ($id !== false && $id > 0) {

            if ($id === (int)$_SESSION['admin_id']) {

                $message = 'You cannot delete your own logged-in account.';
                $messageType = 'danger';

            } else {

                try {

                    $stmt = $conn->prepare(
                        "DELETE FROM admins WHERE id = ?"
                    );

                    $stmt->bind_param('i', $id);
                    $stmt->execute();

                    $deleted = $stmt->affected_rows;
                    $stmt->close();

                    if ($deleted > 0) {

                        header(
                            'Location: manage_admin_info.php?message=' .
                            urlencode('Admin deleted successfully.')
                        );
                        exit;

                    } else {

                        $message = 'Admin record not found.';
                        $messageType = 'danger';
                    }

                } catch (mysqli_sql_exception $e) {

                    error_log('Admin delete error: ' . $e->getMessage());

                    $message = 'Unable to delete this admin record.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

// DISPLAY SUCCESS MESSAGE
if (isset($_GET['message'])) {
    $message = trim($_GET['message']);
    $messageType = 'success';
}

// LOAD ADMIN FOR EDITING
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit']) &&
    ctype_digit((string)$_GET['edit'])
) {

    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT id, name, Marriage_hall_name, username, email,
                address, district, state, pin_code, mobile1, mobile2,
                mobile3, email1, alt_email, status
         FROM admins WHERE id = ?"
    );

    $stmt->bind_param('i', $editId);
    $stmt->execute();

    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {

        $stringDefaults = [
            'name' => 'N/A',
            'Marriage_hall_name' => 'N/A',
            'username' => 'N/A',
            'email' => '',
            'address' => 'N/A',
            'district' => 'N/A',
            'state' => 'N/A',
            'mobile1' => '',
            'mobile2' => '',
            'mobile3' => '',
            'email1' => '',
            'alt_email' => ''
        ];

        foreach ($stringDefaults as $field => $default) {
            if (
                !isset($record[$field]) ||
                trim((string)$record[$field]) === ''
            ) {
                $record[$field] = $default;
            }
        }

        if (
            !isset($record['pin_code']) ||
            trim((string)$record['pin_code']) === ''
        ) {
            $record['pin_code'] = '0';
        }

        $admin = $record;

    } else {

        $message = 'Admin record not found.';
        $messageType = 'danger';
    }
}

// SEARCH ADMIN RECORDS
$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $like = '%' . $search . '%';

    $stmt = $conn->prepare(
        "SELECT id, name, Marriage_hall_name, username, email,
                address, district, state, pin_code, mobile1, mobile2,
                mobile3, email1, alt_email, status, created_at
         FROM admins
         WHERE name LIKE ?
            OR Marriage_hall_name LIKE ?
            OR username LIKE ?
            OR email LIKE ?
            OR address LIKE ?
            OR mobile1 LIKE ?
         ORDER BY id DESC"
    );

    $stmt->bind_param(
        'ssssss',
        $like, $like, $like, $like, $like, $like
    );

    $stmt->execute();
    $adminResult = $stmt->get_result();

} else {

    $adminResult = $conn->query(
        "SELECT id, name, Marriage_hall_name, username, email,
                address, district, state, pin_code, mobile1, mobile2,
                mobile3, email1, alt_email, status, created_at
         FROM admins ORDER BY id DESC"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Admin Information | Paradiso The Venue</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #253047;
        }

        .page-header, .panel {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,.05);
            margin-bottom: 24px;
        }

        .page-header h2 {
            font-weight: 700;
        }

        .panel-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control {
            padding: 10px 12px;
        }

        .btn-gold {
            background: #c49a35;
            color: white;
            border: none;
        }

        .btn-gold:hover {
            background: #a77c1c;
            color: white;
        }

        .table th {
            background: #f7f8fa;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .address-cell {
            min-width: 180px;
            max-width: 280px;
            overflow-wrap: anywhere;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .section-heading {
            font-size: 15px;
            font-weight: 700;
            color: #997321;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin: 8px 0 4px;
        }

        @media (max-width: 767px) {
            .page-header, .panel {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid py-4 px-3 px-lg-4">

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="mb-1">
                <i class="bi bi-building text-warning"></i>
                Manage Admin Information
            </h2>
            <p class="text-muted mb-0">
                Manage venue details, contact information and admin accounts.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= h($messageType) ?> alert-dismissible fade show">
            <?= h($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ADMIN FORM -->
    <div class="panel">

        <h3 class="panel-title">
            <i class="bi bi-person-gear"></i>
            <?= !empty($admin['id']) ? 'Edit Admin Information' : 'Add Admin Information' ?>
        </h3>

        <form method="POST" action="manage_admin_info.php">

            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h($admin['id']) ?>">

            <div class="row g-3">

                <div class="col-12">
                    <div class="section-heading">Admin and Venue Details</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Admin Name *</label>
                    <input type="text" name="name" class="form-control"
                           maxlength="150" required value="<?= h($admin['name']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Marriage Hall Name *</label>
                    <input type="text" name="Marriage_hall_name"
                           class="form-control" maxlength="100" required
                           value="<?= h($admin['Marriage_hall_name']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control"
                           maxlength="100" required autocomplete="username"
                           value="<?= h($admin['username']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Primary Email</label>
                    <input type="email" name="email" class="form-control"
                           maxlength="150" value="<?= h($admin['email']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Password <?= empty($admin['id']) ? '*' : '(leave blank to keep current password)' ?>
                    </label>
                    <input type="password" name="password" class="form-control"
                           minlength="8" autocomplete="new-password"
                           <?= empty($admin['id']) ? 'required' : '' ?>>
                    <small class="text-muted">Minimum 8 characters.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input"
                               id="status" name="status" value="1"
                               <?= (int)$admin['status'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="section-heading">Venue Address</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Full Address *</label>
                    <textarea name="address" class="form-control" rows="2"
                              maxlength="255" required><?= h($admin['address']) ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">District *</label>
                    <input type="text" name="district" class="form-control"
                           maxlength="100" required value="<?= h($admin['district']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">State *</label>
                    <input type="text" name="state" class="form-control"
                           maxlength="50" required value="<?= h($admin['state']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">PIN Code *</label>
                    <input type="text" name="pin_code" class="form-control"
                           maxlength="7" inputmode="numeric"
                           pattern="[0-9]{1,7}" required value="<?= h($admin['pin_code']) ?>">
                </div>

                <div class="col-12">
                    <div class="section-heading">Contact Details</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mobile Number 1</label>
                    <input type="text" name="mobile1" class="form-control"
                           value="<?= h($admin['mobile1']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mobile Number 2</label>
                    <input type="text" name="mobile2" class="form-control"
                           value="<?= h($admin['mobile2']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mobile Number 3</label>
                    <input type="text" name="mobile3" class="form-control"
                           value="<?= h($admin['mobile3']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Contact Email 1</label>
                    <input type="email" name="email1" class="form-control"
                           maxlength="100" value="<?= h($admin['email1']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Alternate Email</label>
                    <input type="email" name="alt_email" class="form-control"
                           maxlength="100" value="<?= h($admin['alt_email']) ?>">
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-gold">
                        <i class="bi bi-save"></i>
                        <?= !empty($admin['id']) ? 'Update Admin Information' : 'Save Admin Information' ?>
                    </button>

                    <?php if (!empty($admin['id'])): ?>
                        <a href="manage_admin_info.php" class="btn btn-secondary">
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

    <!-- ADMIN RECORDS -->
    <div class="panel">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

            <h3 class="panel-title mb-0">
                <i class="bi bi-people"></i> Admin Records
            </h3>

            <form method="GET" action="manage_admin_info.php" class="d-flex gap-2">
                <input type="search" name="search" class="form-control"
                       placeholder="Search admin, venue, username..."
                       value="<?= h($search) ?>">

                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-search"></i>
                </button>

                <?php if ($search !== ''): ?>
                    <a href="manage_admin_info.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">

                <thead>
                <tr>
                    <th>ID</th>
                    <th>Admin / Venue</th>
                    <th>Username</th>
                    <th>Address</th>
                    <th>Contact Numbers</th>
                    <th>Emails</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php if ($adminResult && $adminResult->num_rows > 0): ?>

                    <?php while ($row = $adminResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>

                            <td>
                                <strong><?= h($row['name'] ?: 'N/A') ?></strong><br>
                                <small class="text-muted">
                                    <?= h($row['Marriage_hall_name'] ?: 'N/A') ?>
                                </small>
                            </td>

                            <td><?= h($row['username'] ?: 'N/A') ?></td>

                            <td class="address-cell">
                                <?= h($row['address'] ?: 'N/A') ?><br>
                                <?= h($row['district'] ?: 'N/A') ?>,
                                <?= h($row['state'] ?: 'N/A') ?> -
                                <?= h($row['pin_code'] ?: '0') ?>
                            </td>

                            <td>
                                <?= h($row['mobile1'] ?: 'N/A') ?><br>
                                <?= h($row['mobile2'] ?: 'N/A') ?><br>
                                <?= h($row['mobile3'] ?: 'N/A') ?>
                            </td>

                            <td class="address-cell">
                                <?= h($row['email'] ?: 'N/A') ?><br>
                                <?= h($row['email1'] ?: 'N/A') ?><br>
                                <?= h($row['alt_email'] ?: 'N/A') ?>
                            </td>

                            <td>
                                <?php if ((int)$row['status'] === 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td><?= h($row['created_at']) ?></td>

                            <td>
                                <div class="action-buttons">

                                    <a href="manage_admin_info.php?edit=<?= (int)$row['id'] ?>"
                                       class="btn btn-sm btn-primary" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <form method="POST" action="manage_admin_info.php">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action"
                                               value="toggle_status">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$row['id'] ?>">
                                        <input type="hidden" name="new_status"
                                               value="<?= (int)$row['status'] === 1 ? 0 : 1 ?>">

                                        <button type="submit"
                                                class="btn btn-sm <?= (int)$row['status'] === 1 ? 'btn-warning' : 'btn-success' ?>"
                                                title="Change status">
                                            <i class="bi <?= (int)$row['status'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                        </button>
                                    </form>

                                    <?php if ((int)$row['id'] !== (int)$_SESSION['admin_id']): ?>
                                        <form method="POST" action="manage_admin_info.php"
                                              onsubmit="return confirm('Are you sure you want to delete this admin?');">

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="action"
                                                   value="delete">
                                            <input type="hidden" name="id"
                                                   value="<?= (int)$row['id'] ?>">

                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No admin records found.
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
