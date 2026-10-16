
<?php
session_start();
include 'config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['services_csrf'])) {
    $_SESSION['services_csrf'] = bin2hex(random_bytes(32));
}

function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function go($msg, $type = 'success') {
    $_SESSION['services_msg'] = $msg;
    $_SESSION['services_msg_type'] = $type;
    header('Location: manage_services.php');
    exit;
}

$icons = [
    'bi-building', 'bi-flower1', 'bi-cup-hot', 'bi-car-front',
    'bi-speaker', 'bi-calendar-heart', 'bi-people', 'bi-camera',
    'bi-music-note-beamed', 'bi-snow', 'bi-wifi', 'bi-stars',
    'bi-shield-check', 'bi-house', 'bi-lightning-charge', 'bi-heart',
    'bi-gem', 'bi-tree', 'bi-water', 'bi-stars'
];

$message = $_SESSION['services_msg'] ?? '';
$messageType = $_SESSION['services_msg_type'] ?? 'success';
unset($_SESSION['services_msg'], $_SESSION['services_msg_type']);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['services_csrf'], $_POST['csrf_token'])
        ) {
            throw new RuntimeException('Invalid request token. Refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

        if ($action === 'save') {
            $id = $id ?: 0;
            $title = trim($_POST['service_title'] ?? '');
            $description = trim($_POST['service_description'] ?? '');
            $icon = $_POST['service_icon'] ?? 'bi-building';
            $order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);

            if ($title === '' || $description === '') {
                throw new RuntimeException('Service title and description are required.');
            }
            if (!in_array($icon, $icons, true)) {
                throw new RuntimeException('Select a valid service icon.');
            }
            if ($order === false || $order < 0) {
                throw new RuntimeException('Display order must be zero or greater.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE services
                     SET service_title=?, service_description=?,
                         service_icon=?, sort_order=?
                     WHERE id=?"
                );
                $stmt->bind_param('sssii', $title, $description, $icon, $order, $id);
                $stmt->execute();

                if ($stmt->affected_rows === 0) {
                    $check = $conn->prepare("SELECT id FROM services WHERE id=?");
                    $check->bind_param('i', $id);
                    $check->execute();
                    $exists = $check->get_result()->num_rows > 0;
                    $check->close();

                    if (!$exists) {
                        $stmt->close();
                        throw new RuntimeException('Service record not found.');
                    }
                }

                $stmt->close();
                go('Service updated successfully.');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO services
                    (service_title, service_description, service_icon, sort_order, status)
                    VALUES (?, ?, ?, ?, 1)"
                );
                $stmt->bind_param('sssi', $title, $description, $icon, $order);
                $stmt->execute();
                $stmt->close();
                go('New service added successfully.');
            }
        }

        if ($action === 'toggle' || $action === 'delete') {
            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid service ID.');
            }

            if ($action === 'toggle') {
                $stmt = $conn->prepare(
                    "UPDATE services SET status=IF(status=1,0,1) WHERE id=?"
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $exists = $stmt->affected_rows > 0;
                $stmt->close();

                if (!$exists) {
                    $check = $conn->prepare("SELECT id FROM services WHERE id=?");
                    $check->bind_param('i', $id);
                    $check->execute();
                    $exists = $check->get_result()->num_rows > 0;
                    $check->close();

                    if (!$exists) {
                        throw new RuntimeException('Service record not found.');
                    }
                }

                go('Service status updated.');
            }

            $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            if (!$deleted) {
                throw new RuntimeException('Service record not found.');
            }

            go('Service deleted successfully.');
        }

        throw new RuntimeException('Unknown operation.');
    }
} catch (Throwable $ex) {
    $message = $ex->getMessage();
    $messageType = 'danger';
}

$editing = null;

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM services WHERE id=?");
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$editing) {
            $message = 'Service record not found.';
            $messageType = 'warning';
        }
    }
}

$services = $conn->query(
    "SELECT * FROM services ORDER BY sort_order ASC, id DESC"
);

$total = (int)$conn->query("SELECT COUNT(*) AS n FROM services")->fetch_assoc()['n'];
$active = (int)$conn->query("SELECT COUNT(*) AS n FROM services WHERE status=1")->fetch_assoc()['n'];
$inactive = $total - $active;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Services | Royal Marriage Hall</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root {
    --gold: #bd934c;
    --gold-dark: #947039;
    --sidebar: #211d25;
    --page: #f5f6fa;
    --text: #292631;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: var(--page);
    color: var(--text);
    font-family: Inter, "Segoe UI", Arial, sans-serif;
}
.sidebar {
    width: 250px;
    background: var(--sidebar);
    color: #fff;
    position: fixed;
    inset: 0 auto 0 0;
    padding: 25px 16px;
    overflow-y: auto;
    z-index: 1000;
}
.brand {
    font-size: 20px;
    font-weight: 800;
    padding: 0 10px 25px;
    border-bottom: 1px solid #ffffff20;
    margin-bottom: 25px;
}
.brand span { color: #dfbb7b; }
.side-label {
    color: #a9a2b0;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    padding: 12px;
}
.sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #ded9e3;
    text-decoration: none;
    padding: 12px 13px;
    border-radius: 9px;
    margin-bottom: 5px;
    font-size: 14px;
}
.sidebar a:hover, .sidebar a.active {
    color: #fff;
    background: #bd934c2b;
}
.sidebar a.active { border-left: 3px solid var(--gold); }
.main {
    margin-left: 250px;
    padding: 28px;
}
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 25px;
}
.muted { color: #85818c; }
.page-title { font-size: 27px; font-weight: 800; margin-bottom: 5px; }
.btn-gold {
    background: var(--gold);
    color: white;
    border: 0;
    font-weight: 600;
}
.btn-gold:hover { background: var(--gold-dark); color: white; }
.stat-card, .panel {
    background: #fff;
    border: 1px solid #eceaf0;
    border-radius: 15px;
    box-shadow: 0 5px 18px #25203308;
}
.stat-card {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: #f6eddc;
    color: var(--gold-dark);
    font-size: 23px;
}
.stat-number { font-size: 26px; font-weight: 800; line-height: 1.2; }
.panel { overflow: hidden; margin-bottom: 24px; }
.panel-heading {
    padding: 19px 22px;
    border-bottom: 1px solid #eeecf1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    font-weight: 750;
}
.panel-body { padding: 22px; }
.form-label { font-weight: 650; font-size: 13px; }
.form-control, .form-select {
    border-color: #e2dfe8;
    padding: 10px 12px;
    border-radius: 8px;
}
.form-control:focus, .form-select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 .2rem #bd934c20;
}
.table { margin: 0; }
.table thead th {
    background: #faf9fc;
    color: #797582;
    font-size: 11px;
    letter-spacing: .7px;
    text-transform: uppercase;
    padding: 15px 13px;
    white-space: nowrap;
}
.table td { padding: 15px 13px; vertical-align: middle; }
.service-symbol {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    color: var(--gold-dark);
    background: #f8f0e1;
    font-size: 23px;
}
.status-pill {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.status-active { background: #e5f7ed; color: #16804a; }
.status-inactive { background: #eeeef1; color: #6b6872; }
.action-btn {
    width: 33px;
    height: 33px;
    display: inline-grid;
    place-items: center;
    border-radius: 8px;
}
.icon-preview { color: var(--gold-dark); font-size: 26px; }
.mobile-menu { display: none; }
@media(max-width: 900px) {
    .sidebar { width: 220px; }
    .main { margin-left: 220px; padding: 20px; }
}
@media(max-width: 700px) {
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 15px; }
    .mobile-menu { display: inline-block; }
    .page-title { font-size: 22px; }
    .topbar { align-items: flex-start; }
    .stat-card { padding: 15px; }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="brand"><i class="bi bi-gem me-2"></i>ROYAL <span>HALL</span></div>

    <div class="side-label">MAIN MENU</div>
    <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="manage_banner.php"><i class="bi bi-images"></i> Manage Banner</a>
    <a href="manage_about.php"><i class="bi bi-info-circle"></i> Manage About</a>
    <a href="manage_services.php" class="active"><i class="bi bi-grid"></i> Manage Services</a>
    <a href="manage_gallery.php"><i class="bi bi-camera"></i> Manage Gallery</a>
    <a href="manage_booking.php"><i class="bi bi-calendar-check"></i> Manage Bookings</a>

    <div class="side-label mt-4">WEBSITE</div>
    <a href="dashboard.php" target="_blank"><i class="bi bi-globe"></i> Go to Dashoard</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</aside>

<main class="main">

    <div class="topbar">
        <div>
            <div class="muted small mb-1">ADMINISTRATION / WEBSITE CONTENT</div>
            <div class="page-title">Manage Services</div>
            <div class="muted">Manage your venue facilities and services.</div>
        </div>
        <button class="btn btn-gold px-3"
                onclick="document.getElementById('serviceForm').scrollIntoView({behavior:'smooth'});">
            <i class="bi bi-plus-lg me-1"></i> Add Service
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= h($messageType) ?> alert-dismissible fade show">
            <?= h($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-grid"></i></div>
                <div><div class="muted small">TOTAL SERVICES</div>
                    <div class="stat-number"><?= $total ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div><div class="muted small">ACTIVE SERVICES</div>
                    <div class="stat-number"><?= $active ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-pause-circle"></i></div>
                <div><div class="muted small">INACTIVE SERVICES</div>
                    <div class="stat-number"><?= $inactive ?></div></div>
            </div>
        </div>
    </div>

    <!-- ADD / EDIT SERVICE -->
    <div class="panel" id="serviceForm">
        <div class="panel-heading">
            <span>
                <i class="bi bi-pencil-square me-2"></i>
                <?= $editing ? 'Edit Service' : 'Add New Service' ?>
            </span>
            <?php if ($editing): ?>
                <a href="manage_services.php" class="btn btn-sm btn-outline-secondary">
                    Cancel
                </a>
            <?php endif; ?>
        </div>

        <div class="panel-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['services_csrf']) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label">Service Title *</label>
                        <input type="text" name="service_title" maxlength="150"
                               class="form-control" required
                               placeholder="e.g. Spacious Hall"
                               value="<?= h($editing['service_title'] ?? '') ?>">
                    </div>

                    <div class="col-lg-6">
                        <label class="form-label">Service Icon *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i id="iconPreview" class="icon-preview bi <?= h($editing['service_icon'] ?? 'bi-building') ?>"></i>
                            </span>
                            <select name="service_icon" id="service_icon" class="form-select" required>
                                <?php foreach ($icons as $icon): ?>
                                <option value="<?= h($icon) ?>"
                                    <?= ($editing['service_icon'] ?? 'bi-building') === $icon ? 'selected' : '' ?>>
                                    <?= h($icon) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description *</label>
                        <textarea name="service_description" rows="3"
                                  class="form-control" required
                                  placeholder="Enter the service description..."><?= h($editing['service_description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Display Order</label>
                        <input type="number" min="0" step="1" name="sort_order"
                               class="form-control"
                               value="<?= h($editing['sort_order'] ?? 0) ?>">
                        <small class="muted">Lower numbers appear first.</small>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-gold px-4">
                            <i class="bi bi-check2-circle me-1"></i>
                            <?= $editing ? 'Save Changes' : 'Create Service' ?>
                        </button>
                        <?php if (!$editing): ?>
                            <button type="reset" class="btn btn-outline-secondary ms-2">
                                Reset
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- SERVICE LIST -->
    <div class="panel">
        <div class="panel-heading">
            <span><i class="bi bi-list-ul me-2"></i>All Services</span>
            <span class="badge text-bg-light"><?= $total ?> records</span>
        </div>

        <div class="p-3 border-bottom">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" id="serviceSearch" class="form-control"
                       placeholder="Search by title, description or status...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" id="servicesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Icon</th>
                        <th>Service Details</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($services->num_rows === 0): ?>
                    <tr><td colspan="6" class="text-center py-5 muted">
                        No services added yet.
                    </td></tr>
                <?php else: ?>
                    <?php while ($row = $services->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$row['id'] ?></td>
                        <td>
                            <div class="service-symbol">
                                <i class="bi <?= h($row['service_icon']) ?>"></i>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold"><?= h($row['service_title']) ?></div>
                            <div class="muted small">
                                <?= h(mb_strimwidth($row['service_description'], 0, 100, '...')) ?>
                            </div>
                        </td>
                        <td><?= (int)$row['sort_order'] ?></td>
                        <td>
                            <?php if ($row['status'] == 1): ?>
                                <span class="status-pill status-active">● Active</span>
                            <?php else: ?>
                                <span class="status-pill status-inactive">● Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="?edit=<?= (int)$row['id'] ?>#serviceForm"
                                   class="btn btn-sm btn-outline-primary action-btn"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['services_csrf']) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-warning action-btn"
                                            type="submit" title="Toggle status">
                                        <i class="bi bi-power"></i>
                                    </button>
                                </form>

                                <form method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this service?');">
                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['services_csrf']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger action-btn"
                                            type="submit" title="Delete">
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

    <div class="text-center muted small py-2">
        Royal Marriage Hall &copy; <?= date('Y') ?> | Admin Panel
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const iconSelect = document.getElementById('service_icon');
const iconPreview = document.getElementById('iconPreview');

iconSelect.addEventListener('change', function () {
    iconPreview.className = 'icon-preview bi ' + this.value;
});

document.getElementById('serviceSearch').addEventListener('input', function () {
    const term = this.value.toLowerCase();

    document.querySelectorAll('#servicesTable tbody tr').forEach(function (row) {
        row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
    });
});
</script>
</body>
</html>
