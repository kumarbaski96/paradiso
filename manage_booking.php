
<?php
session_start();
require_once __DIR__ . "/config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(32));
}

/* Enable this guard if your admin login uses admin_id.
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
*/

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function getBookingDays(string $start, string $end): int {
    $s = new DateTime($start);
    $e = new DateTime($end);

    if ($e < $s) {
        throw new Exception("End date cannot be earlier than start date.");
    }

    return (int)$s->diff($e)->days + 1;
}

function getPaymentDetails(float $total, float $paid): array {
    $total = round(max(0, $total), 2);
    $paid = round(max(0, min($paid, $total)), 2);
    $remaining = round($total - $paid, 2);

    if ($total > 0 && $remaining <= 0) {
        $status = "Paid";
    } elseif ($paid > 0) {
        $status = "Partially Paid";
    } else {
        $status = "Pending";
    }

    return [$paid, $remaining, $status];
}

function badgeClass(string $status): string {
    return strtolower(str_replace(' ', '-', $status));
}

/* Render booking table rows */
function renderRows(mysqli_result $result): string {
    ob_start();

    if ($result->num_rows === 0) {
        echo '<tr><td colspan="10" class="text-center py-5">
                <div class="empty-icon"><i class="bi bi-search"></i></div>
                <strong>No bookings found</strong>
                <div class="text-muted small">Try another search term.</div>
              </td></tr>';
    } else {
        while ($r = $result->fetch_assoc()) {
            $id = (int)$r['id'];
            $payment = $r['payment_status'] ?? 'Pending';
            $booking = $r['booking_status'] ?? 'Enquiry';

            echo '<tr>';

            echo '<td><span class="booking-id">#' . $id . '</span></td>';

            echo '<td>
                    <div class="customer-name">' . h($r['name']) . '</div>
                    <div class="small text-muted">' . h($r['email']) . '</div>
                  </td>';

            echo '<td>' . h($r['phone']) . '</td>';

            echo '<td>
                    <div>' . h($r['start_date']) . '</div>
                    <div class="small text-muted">to ' . h($r['end_date']) . '</div>
                  </td>';

            echo '<td>' . h($r['event']) . '</td>';

            echo '<td>
                    <div class="amount">₹' . number_format((float)($r['total_amount'] ?? 0), 2) . '</div>
                    <div class="small text-muted">Paid: ₹' . number_format((float)($r['paid_amount'] ?? 0), 2) . '</div>
                    <div class="small remaining">Due: ₹' . number_format((float)($r['rest_amount'] ?? 0), 2) . '</div>
                  </td>';

            echo '<td><span class="status-pill ' . h(badgeClass($payment)) . '">' . h($payment) . '</span></td>';

            echo '<td><span class="status-pill ' . h(badgeClass($booking)) . '">' . h($booking) . '</span></td>';

            echo '<td>' . h($r['total_days']) . ' day(s)</td>';

            echo '<td>
                    <div class="action-buttons">
                      <a href="?edit=' . $id . '" class="btn btn-sm btn-edit">
                        <i class="bi bi-pencil-square"></i> Edit
                      </a>

                      <form method="POST" class="d-inline"
                            onsubmit="return confirm(\'Delete booking #' . $id . '? This cannot be undone.\');">
                        <input type="hidden" name="csrf_token" value="' .
                            h($_SESSION['booking_csrf']) . '">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="' . $id . '">
                        <button type="submit" class="btn btn-sm btn-delete">
                          <i class="bi bi-trash"></i> Delete
                        </button>
                      </form>
                    </div>
                  </td>';

            echo '</tr>';
        }
    }

    return ob_get_clean();
}

/* Get paginated booking results */
function getPageData(mysqli $conn, string $search, int $page, int $perPage = 10): array {
    $page = max(1, $page);
    $term = '%' . $search . '%';

    $where = " WHERE name LIKE ?
               OR phone LIKE ?
               OR email LIKE ?
               OR CAST(id AS CHAR) LIKE ?
               OR CAST(start_date AS CHAR) LIKE ?
               OR CAST(end_date AS CHAR) LIKE ? ";

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM booking" . $where);
    $countStmt->bind_param("ssssss", $term, $term, $term, $term, $term, $term);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare(
        "SELECT * FROM booking" . $where . " ORDER BY id DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param(
        "ssssssii",
        $term, $term, $term, $term, $term, $term, $perPage, $offset
    );
    $stmt->execute();
    $rows = $stmt->get_result();

    $html = renderRows($rows);
    $stmt->close();

    return [
        'html' => $html,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'from' => $total ? $offset + 1 : 0,
        'to' => min($offset + $perPage, $total)
    ];
}

/* AJAX search and pagination endpoint in this same file */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $search = trim((string)($_GET['search'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));

        echo json_encode(getPageData($conn, $search, $page));
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to load bookings. Please check the database and table columns.']);
    }

    exit;
}

$errorMessage = '';
$successMessage = '';

/* Handle Add, Update and Delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $_SESSION['booking_csrf'] ?? '',
            (string)($_POST['csrf_token'] ?? '')
        )) {
            throw new Exception("Invalid form token. Refresh the page and try again.");
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id < 1) {
                throw new Exception("Invalid booking ID.");
            }

            $stmt = $conn->prepare("DELETE FROM booking WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['booking_flash'] = "Booking deleted successfully.";
            header("Location: manage_booking.php");
            exit;
        }

        if ($action === 'create' || $action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $event = trim($_POST['event'] ?? '');
            $start = trim($_POST['start_date'] ?? '');
            $end = trim($_POST['end_date'] ?? '');
            $guests = max(0, (int)($_POST['guests'] ?? 0));
            $message = trim($_POST['message'] ?? '');
            $total = round(max(0, (float)($_POST['total_amount'] ?? 0)), 2);
            $paidInput = round(max(0, (float)($_POST['paid_amount'] ?? 0)), 2);
            $bookingStatus = $_POST['booking_status'] ?? 'Enquiry';

            if ($name === '' || $phone === '' || $start === '' || $end === '') {
                throw new Exception("Name, mobile number, start date and end date are required.");
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Please enter a valid email address.");
            }

            $allowedStatuses = ['Enquiry', 'Confirmed', 'Cancelled', 'Completed'];
            if (!in_array($bookingStatus, $allowedStatuses, true)) {
                $bookingStatus = 'Enquiry';
            }

            $days = getBookingDays($start, $end);
            [$paid, $remaining, $paymentStatus] = getPaymentDetails($total, $paidInput);

            if ($action === 'create') {
                $stmt = $conn->prepare("
                    INSERT INTO booking
                    (name, phone, email, event, start_date, end_date,
                     total_days, guests, message, total_amount, paid_amount,
                     rest_amount, payment_status, booking_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssssssiisdddss",
                    $name, $phone, $email, $event, $start, $end,
                    $days, $guests, $message, $total, $paid, $remaining,
                    $paymentStatus, $bookingStatus
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['booking_flash'] = "Booking added successfully.";
            } else {
                if ($id < 1) {
                    throw new Exception("Invalid booking ID.");
                }

                $stmt = $conn->prepare("
                    UPDATE booking SET
                        name = ?, phone = ?, email = ?, event = ?,
                        start_date = ?, end_date = ?, total_days = ?,
                        guests = ?, message = ?, total_amount = ?,
                        paid_amount = ?, rest_amount = ?,
                        payment_status = ?, booking_status = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssssssiisdddssi",
                    $name, $phone, $email, $event, $start, $end,
                    $days, $guests, $message, $total, $paid, $remaining,
                    $paymentStatus, $bookingStatus, $id
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['booking_flash'] = "Booking updated successfully.";
            }

            header("Location: manage_booking.php");
            exit;
        }

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if (!empty($_SESSION['booking_flash'])) {
    $successMessage = $_SESSION['booking_flash'];
    unset($_SESSION['booking_flash']);
}

/* Load selected booking for editing */
$editBooking = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM booking WHERE id = ?");
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $editBooking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$editBooking) {
            $errorMessage = "Booking record not found.";
        }
    }
}

/* Dashboard summary */
$summary = [
    'total' => 0,
    'confirmed' => 0,
    'revenue' => 0,
    'due' => 0
];

$summaryResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN booking_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
        COALESCE(SUM(paid_amount), 0) AS revenue,
        COALESCE(SUM(rest_amount), 0) AS due
    FROM booking
");

if ($summaryResult) {
    $summary = array_merge($summary, $summaryResult->fetch_assoc());
}

$initial = getPageData($conn, '', 1, 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Bookings | Admin Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --navy: #18243a;
    --gold: #c69a45;
    --light-bg: #f4f6fa;
    --border: #e7eaf0;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: var(--light-bg);
    color: #273247;
    font-family: Arial, sans-serif;
}
.page-wrap { max-width: 1600px; margin: auto; padding: 24px; }
.topbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 15px; flex-wrap: wrap; margin-bottom: 22px;
}
.page-title { font-weight: 800; margin: 0; color: var(--navy); }
.subtitle { color: #7c8493; margin-top: 5px; font-size: 14px; }
.btn-dashboard {
    background: var(--navy); color: white; border-radius: 10px;
    padding: 11px 17px; text-decoration: none; font-weight: 600;
}
.btn-dashboard:hover { background: #263a5b; color: white; }
.summary-card {
    background: white; border: 1px solid var(--border); border-radius: 15px;
    padding: 19px; height: 100%; box-shadow: 0 3px 12px #18243a08;
}
.summary-label { color: #7b8495; font-size: 13px; }
.summary-number { color: var(--navy); font-size: 25px; font-weight: 800; margin-top: 7px; }
.panel {
    background: white; border: 1px solid var(--border); border-radius: 15px;
    margin-bottom: 22px; overflow: hidden;
}
.panel-heading {
    display: flex; justify-content: space-between; align-items: center;
    gap: 12px; flex-wrap: wrap; padding: 18px 20px;
    border-bottom: 1px solid var(--border);
}
.panel-heading h5 { margin: 0; font-weight: 800; color: var(--navy); }
.panel-body { padding: 20px; }
.form-label { font-weight: 600; font-size: 13px; color: #596579; }
.form-control, .form-select { min-height: 43px; border-color: #dfe4ec; border-radius: 9px; }
textarea.form-control { min-height: 90px; }
.btn-primary-custom { background: var(--navy); border: 0; color: white; }
.btn-primary-custom:hover { background: #293e60; color: white; }
.btn-cancel { background: #edf0f5; color: #273247; }
.search-wrap { position: relative; width: min(100%, 540px); }
.search-wrap .bi-search {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #818b9b;
}
.search-input { padding-left: 42px; }
.search-hint { font-size: 12px; color: #8992a1; margin-top: 6px; }
.live-indicator { font-size: 12px; color: #16835d; white-space: nowrap; }
.live-dot {
    display: inline-block; width: 7px; height: 7px; background: #20ad78;
    border-radius: 50%; margin-right: 5px;
}

/* Only the booking data area scrolls vertically */
.booking-scroll {
    max-height: 540px;
    overflow: auto;
    width: 100%;
    overscroll-behavior: contain;
}
.booking-table { margin: 0; min-width: 1250px; }
.booking-table thead th {
    position: sticky; top: 0; z-index: 2; background: #f5f7fb;
    color: #626e82; font-size: 11px; text-transform: uppercase;
    letter-spacing: .5px; white-space: nowrap; padding: 14px 13px;
    border-bottom: 1px solid #e1e6ef;
}
.booking-table tbody td {
    padding: 14px 13px; vertical-align: middle;
    border-color: #edf0f5; font-size: 13px;
}
.booking-table tbody tr:hover { background: #fafbfd; }
.booking-id { color: #a87a28; font-weight: 800; }
.customer-name { color: var(--navy); font-weight: 700; }
.amount { font-weight: 700; white-space: nowrap; }
.remaining { color: #c04d4d; }
.status-pill {
    display: inline-block; padding: 6px 9px; border-radius: 20px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}
.pending, .enquiry { background: #fff3d6; color: #946715; }
.partially-paid { background: #e7f1ff; color: #2764a5; }
.paid, .confirmed, .completed { background: #dcf6e8; color: #19764a; }
.cancelled, .refunded { background: #ffe6e6; color: #b33b3b; }
.btn-edit { background: #e7f0ff; color: #275ca2; }
.btn-delete { background: #ffe9e9; color: #b53a3a; }
.btn-edit:hover { background: #d6e5ff; color: #174b91; }
.btn-delete:hover { background: #ffdada; color: #962a2a; }
.action-buttons { display: flex; gap: 6px; white-space: nowrap; }
.action-buttons form { margin: 0; }
.action-buttons .btn { font-size: 12px; }
.pagination-bar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap; padding: 15px 20px;
    border-top: 1px solid var(--border); background: #fff;
}
.page-info { color: #687386; font-size: 13px; }
.pagination-controls { display: flex; align-items: center; gap: 9px; }
.page-btn {
    border: 1px solid #dfe4ec; background: white; border-radius: 8px;
    padding: 8px 13px; font-size: 13px; font-weight: 600; color: var(--navy);
}
.page-btn:hover:not(:disabled) { background: var(--navy); color: white; }
.page-btn:disabled { opacity: .4; cursor: not-allowed; }
.page-number { font-size: 13px; color: #6c7585; min-width: 85px; text-align: center; }
.empty-icon { font-size: 25px; color: #9aa3b1; margin-bottom: 8px; }
#loadingMessage { display: none; font-size: 12px; color: #7b8495; }
@media(max-width: 768px) {
    .page-wrap { padding: 13px; }
    .panel-body { padding: 14px; }
    .panel-heading { padding: 15px; }
    .summary-number { font-size: 21px; }
    .booking-scroll { max-height: 480px; }
    .pagination-bar { padding: 13px; }
}
</style>
</head>

<body>
<div class="page-wrap">

    <header class="topbar">
        <div>
            <h2 class="page-title"><i class="bi bi-calendar2-check"></i> Manage Bookings</h2>
            <div class="subtitle">Search, manage bookings and track payments.</div>
        </div>

        <!-- Change dashboard.php if your dashboard has a different filename -->
        <a href="dashboard.php" class="btn-dashboard">
            <i class="bi bi-arrow-left-circle"></i> Go Back to Dashboard
        </a>
    </header>

    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= h($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-label"><i class="bi bi-journal-bookmark"></i> Total Bookings</div>
                <div class="summary-number"><?= number_format((int)$summary['total']) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-label"><i class="bi bi-calendar-check"></i> Confirmed</div>
                <div class="summary-number"><?= number_format((int)$summary['confirmed']) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-label"><i class="bi bi-currency-rupee"></i> Amount Received</div>
                <div class="summary-number">₹<?= number_format((float)$summary['revenue'], 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-label"><i class="bi bi-wallet2"></i> Amount Due</div>
                <div class="summary-number">₹<?= number_format((float)$summary['due'], 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Add / Edit Form -->
    <section class="panel" id="bookingForm">
        <div class="panel-heading">
            <h5>
                <i class="bi bi-<?= $editBooking ? 'pencil-square' : 'plus-circle' ?>"></i>
                <?= $editBooking ? 'Edit Booking #' . (int)$editBooking['id'] : 'Add New Booking' ?>
            </h5>
            <?php if ($editBooking): ?>
                <a href="manage_booking.php" class="btn btn-sm btn-cancel">
                    <i class="bi bi-x-circle"></i> Cancel Editing
                </a>
            <?php endif; ?>
        </div>

        <div class="panel-body">
            <form method="POST" action="manage_booking.php<?= $editBooking ? '?edit=' . (int)$editBooking['id'] : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['booking_csrf']) ?>">
                <input type="hidden" name="action" value="<?= $editBooking ? 'update' : 'create' ?>">
                <input type="hidden" name="id" value="<?= (int)($editBooking['id'] ?? 0) ?>">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= h($editBooking['name'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Mobile Number *</label>
                        <input type="text" name="phone" class="form-control" required
                               value="<?= h($editBooking['phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= h($editBooking['email'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Event Type</label>
                        <select name="event" class="form-select">
                            <?php
                            $eventValue = $editBooking['event'] ?? '';
                            $events = ['Wedding', 'Reception', 'Birthday', 'Anniversary', 'Engagement', 'Corporate Event', 'Other'];
                            ?>
                            <option value="">Select Event</option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?= h($ev) ?>" <?= $eventValue === $ev ? 'selected' : '' ?>>
                                    <?= h($ev) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" required
                               value="<?= h($editBooking['start_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" class="form-control" required
                               value="<?= h($editBooking['end_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Number of Guests</label>
                        <input type="number" name="guests" min="0" class="form-control"
                               value="<?= h($editBooking['guests'] ?? 0) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Total Amount (₹)</label>
                        <input type="number" name="total_amount" id="total_amount"
                               min="0" step="0.01" class="form-control"
                               value="<?= h($editBooking['total_amount'] ?? '0.00') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Paid Amount (₹)</label>
                        <input type="number" name="paid_amount" id="paid_amount"
                               min="0" step="0.01" class="form-control"
                               value="<?= h($editBooking['paid_amount'] ?? '0.00') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Remaining Amount (₹)</label>
                        <input type="text" id="remaining_preview" class="form-control" readonly
                               value="<?= number_format((float)($editBooking['rest_amount'] ?? 0), 2, '.', '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Payment Status</label>
                        <input type="text" id="payment_preview" class="form-control" readonly
                               value="<?= h($editBooking['payment_status'] ?? 'Pending') ?>">
                        <div class="form-text">Automatically calculated from total and paid amount.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Booking Status</label>
                        <?php $bookingValue = $editBooking['booking_status'] ?? 'Enquiry'; ?>
                        <select name="booking_status" class="form-select">
                            <?php foreach (['Enquiry', 'Confirmed', 'Cancelled', 'Completed'] as $st): ?>
                                <option value="<?= h($st) ?>" <?= $bookingValue === $st ? 'selected' : '' ?>>
                                    <?= h($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Message / Notes</label>
                        <textarea name="message" class="form-control" rows="2"><?= h($editBooking['message'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary-custom px-4 py-2">
                            <i class="bi bi-save"></i>
                            <?= $editBooking ? 'Update Booking' : 'Save Booking' ?>
                        </button>

                        <?php if ($editBooking): ?>
                            <a href="manage_booking.php" class="btn btn-cancel px-4 py-2">Cancel</a>
                        <?php else: ?>
                            <button type="reset" class="btn btn-cancel px-4 py-2">Clear Form</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Booking records: header and search stay outside the scroll area -->
    <section class="panel" id="bookingRecords">
        <div class="panel-heading">
            <h5><i class="bi bi-list-ul"></i> Booking Records</h5>
            <div class="live-indicator"><span class="live-dot"></span> Live search enabled</div>
        </div>

        <div class="panel-body pb-3">
            <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="search" id="bookingSearch" class="form-control search-input"
                           placeholder="Search ID, name, mobile, email or date..."
                           autocomplete="off">
                    <div class="search-hint">
                        Results update as you type. Dates can be searched as YYYY-MM-DD.
                    </div>
                </div>

                <div id="loadingMessage"><i class="bi bi-arrow-repeat"></i> Loading records...</div>
            </div>
        </div>

        <div class="booking-scroll" id="bookingScroll">
            <table class="table booking-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Booking Dates</th>
                        <th>Event</th>
                        <th>Payment Details</th>
                        <th>Payment Status</th>
                        <th>Booking Status</th>
                        <th>Days</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="bookingRows">
                    <?= $initial['html'] ?>
                </tbody>
            </table>
        </div>

        <!-- Previous / Next pagination -->
        <div class="pagination-bar">
            <div class="page-info" id="pageInfo">
                Showing <?= (int)$initial['from'] ?>–<?= (int)$initial['to'] ?>
                of <?= (int)$initial['total'] ?> bookings
            </div>

            <div class="pagination-controls">
                <button type="button" class="page-btn" id="prevPage" disabled>
                    <i class="bi bi-chevron-left"></i> Previous
                </button>

                <div class="page-number" id="pageNumber">
                    Page 1 of <?= (int)$initial['pages'] ?>
                </div>

                <button type="button" class="page-btn" id="nextPage"
                        <?= $initial['pages'] <= 1 ? 'disabled' : '' ?>>
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* Live search and 10-record pagination */
const searchInput = document.getElementById('bookingSearch');
const bookingRows = document.getElementById('bookingRows');
const pageInfo = document.getElementById('pageInfo');
const pageNumber = document.getElementById('pageNumber');
const prevButton = document.getElementById('prevPage');
const nextButton = document.getElementById('nextPage');
const loadingMessage = document.getElementById('loadingMessage');
const bookingScroll = document.getElementById('bookingScroll');

let currentPage = 1;
let totalPages = <?= (int)$initial['pages'] ?>;
let debounceTimer = null;
let requestController = null;

async function loadBookings(page = 1, resetScroll = false) {
    currentPage = page;

    if (requestController) {
        requestController.abort();
    }

    requestController = new AbortController();
    loadingMessage.style.display = 'block';
    prevButton.disabled = true;
    nextButton.disabled = true;

    const params = new URLSearchParams({
        ajax: '1',
        search: searchInput.value.trim(),
        page: String(page)
    });

    try {
        const response = await fetch('manage_booking.php?' + params.toString(), {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: requestController.signal,
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Could not load booking records.');
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        bookingRows.innerHTML = data.html;
        currentPage = data.page;
        totalPages = data.pages;

        pageInfo.textContent =
            `Showing ${data.from}–${data.to} of ${data.total} bookings`;

        pageNumber.textContent =
            `Page ${data.page} of ${data.pages}`;

        prevButton.disabled = data.page <= 1;
        nextButton.disabled = data.page >= data.pages;

        if (resetScroll) {
            bookingScroll.scrollTop = 0;
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            bookingRows.innerHTML =
                '<tr><td colspan="10" class="text-center text-danger py-4">' +
                'Unable to load bookings. Please refresh the page and try again.' +
                '</td></tr>';
            pageInfo.textContent = 'Unable to load records';
            pageNumber.textContent = '';
        }
    } finally {
        loadingMessage.style.display = 'none';
    }
}

/* Wait briefly after typing to avoid sending a request for every keystroke */
searchInput.addEventListener('input', function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
        loadBookings(1, true);
    }, 250);
});

prevButton.addEventListener('click', function () {
    if (currentPage > 1) {
        loadBookings(currentPage - 1, true);
    }
});

nextButton.addEventListener('click', function () {
    if (currentPage < totalPages) {
        loadBookings(currentPage + 1, true);
    }
});

/* Calculate remaining amount immediately in the form */
const totalInput = document.getElementById('total_amount');
const paidInput = document.getElementById('paid_amount');
const remainingPreview = document.getElementById('remaining_preview');
const paymentPreview = document.getElementById('payment_preview');

function updatePaymentPreview() {
    let total = Math.max(0, parseFloat(totalInput.value) || 0);
    let paid = Math.max(0, parseFloat(paidInput.value) || 0);

    if (paid > total) {
        paid = total;
    }

    const remaining = Math.max(0, total - paid);

    remainingPreview.value = remaining.toFixed(2);

    if (total > 0 && remaining <= 0) {
        paymentPreview.value = 'Paid';
    } else if (paid > 0) {
        paymentPreview.value = 'Partially Paid';
    } else {
        paymentPreview.value = 'Pending';
    }
}

totalInput.addEventListener('input', updatePaymentPreview);
paidInput.addEventListener('input', updatePaymentPreview);
updatePaymentPreview();
</script>
</body>
</html>
