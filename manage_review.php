
<?php
session_start();
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
include 'config/db.php';

// Enable this check if your project uses an admin session.
// Change 'admin_id' to match your login system.
// if (empty($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit;
// }

// Ensure database connection is available
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Check includes/dbconn.php and the $conn variable.');
}

// CSRF token
if (empty($_SESSION['review_csrf_token'])) {
    $_SESSION['review_csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectReviews()
{
    header('Location: manage_review.php');
    exit;
}

// Flash message
$message = $_SESSION['review_flash'] ?? '';
$messageType = $_SESSION['review_flash_type'] ?? 'success';
unset($_SESSION['review_flash'], $_SESSION['review_flash_type']);

function setReviewFlash($message, $type = 'success')
{
    $_SESSION['review_flash'] = $message;
    $_SESSION['review_flash_type'] = $type;
}

// Handle all POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['review_csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    // ADD OR UPDATE REVIEW
    if ($action === 'save') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $id = $id === false ? 0 : max(0, $id);

        $customerName = trim($_POST['customer_name'] ?? '');
        $eventType = trim($_POST['event_type'] ?? '');
        $reviewText = trim($_POST['review_text'] ?? '');
        $rating = filter_var($_POST['rating'] ?? 5, FILTER_VALIDATE_INT);
        $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);

        if ($rating === false || $rating < 1 || $rating > 5) {
            $rating = 0;
        }

        if ($sortOrder === false || $sortOrder < 0) {
            $sortOrder = -1;
        }

        if (
            $customerName === '' ||
            $eventType === '' ||
            $reviewText === '' ||
            mb_strlen($customerName) > 150 ||
            mb_strlen($eventType) > 150 ||
            $rating < 1 ||
            $sortOrder < 0
        ) {
            setReviewFlash('Please enter valid details in all fields.', 'danger');
            redirectReviews();
        }

        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE reviews
                 SET customer_name = ?, event_type = ?, review_text = ?,
                     rating = ?, sort_order = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                'sssiii',
                $customerName,
                $eventType,
                $reviewText,
                $rating,
                $sortOrder,
                $id
            );

            if ($stmt->execute()) {
                setReviewFlash('Customer review updated successfully.');
            } else {
                setReviewFlash('Unable to update the review.', 'danger');
            }

            $stmt->close();
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO reviews
                    (customer_name, event_type, review_text, rating, status, sort_order)
                 VALUES (?, ?, ?, ?, 1, ?)"
            );

            $stmt->bind_param(
                'sssii',
                $customerName,
                $eventType,
                $reviewText,
                $rating,
                $sortOrder
            );

            if ($stmt->execute()) {
                setReviewFlash('Customer review added successfully.');
            } else {
                setReviewFlash('Unable to add the review.', 'danger');
            }

            $stmt->close();
        }

        redirectReviews();
    }

    // ACTIVATE / DEACTIVATE REVIEW
    if ($action === 'toggle') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            setReviewFlash('Invalid review ID.', 'danger');
            redirectReviews();
        }

        $stmt = $conn->prepare(
            "UPDATE reviews SET status = IF(status = 1, 0, 1) WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            setReviewFlash('Review status changed successfully.');
        } else {
            setReviewFlash('Review not found or status unchanged.', 'warning');
        }

        $stmt->close();
        redirectReviews();
    }

    // DELETE REVIEW
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            setReviewFlash('Invalid review ID.', 'danger');
            redirectReviews();
        }

        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            setReviewFlash('Review deleted successfully.');
        } else {
            setReviewFlash('Review not found or could not be deleted.', 'danger');
        }

        $stmt->close();
        redirectReviews();
    }
}

// Load review for editing
$editReview = [
    'id' => 0,
    'customer_name' => '',
    'event_type' => '',
    'review_text' => '',
    'rating' => 5,
    'sort_order' => 0
];

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM reviews WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();

    $resultEdit = $stmt->get_result();

    if ($row = $resultEdit->fetch_assoc()) {
        $editReview = $row;
    } else {
        setReviewFlash('Review not found.', 'warning');
        $stmt->close();
        redirectReviews();
    }

    $stmt->close();
}

// Search and list reviews
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare(
        "SELECT * FROM reviews
         WHERE customer_name LIKE ?
            OR event_type LIKE ?
            OR review_text LIKE ?
         ORDER BY sort_order ASC, id DESC"
    );
    $stmt->bind_param('sss', $like, $like, $like);
} else {
    $stmt = $conn->prepare(
        "SELECT * FROM reviews ORDER BY sort_order ASC, id DESC"
    );
}

$stmt->execute();
$reviewsResult = $stmt->get_result();

// Dashboard counts
$totalReviews = 0;
$activeReviews = 0;
$inactiveReviews = 0;

$countResult = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 1), 0) AS active,
        COALESCE(SUM(status = 0), 0) AS inactive
     FROM reviews"
);

if ($countResult) {
    $counts = $countResult->fetch_assoc();
    $totalReviews = (int)$counts['total'];
    $activeReviews = (int)$counts['active'];
    $inactiveReviews = (int)$counts['inactive'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews | Admin Panel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            background: #f5f6fa;
            font-family: Arial, sans-serif;
            color: #263238;
        }

        .page-header {
            background: linear-gradient(135deg, #202a44, #414e70);
            color: #fff;
            padding: 26px;
            border-radius: 14px;
            margin-bottom: 24px;
        }

        .page-header h2 {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .stat-card, .content-card {
            background: #fff;
            border: 1px solid #e9edf3;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(25, 35, 55, .04);
        }

        .stat-card {
            padding: 20px;
            height: 100%;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: #eef0ff;
            color: #4957bd;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 700;
            margin-top: 12px;
        }

        .content-card {
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-heading {
            font-weight: 700;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control, .form-select {
            padding: 11px 12px;
            border-radius: 8px;
        }

        .btn {
            border-radius: 8px;
        }

        .table thead th {
            background: #f0f2f7;
            white-space: nowrap;
            font-size: 13px;
            text-transform: uppercase;
        }

        .table td {
            vertical-align: middle;
        }

        .review-preview {
            min-width: 180px;
            max-width: 330px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .stars {
            color: #e5a500;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            background: #edeaff;
            color: #5145a5;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 9px;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .action-buttons form {
            display: inline;
            margin: 0;
        }

        @media (max-width: 767px) {
            .page-header, .content-card {
                padding: 18px;
            }

            .page-header .btn {
                margin-top: 12px;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid px-3 px-lg-4 py-4">

    <!-- Page Header -->
    <div class="page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h2><i class="bi bi-chat-square-quote me-2"></i>Manage Customer Reviews</h2>
            <p class="mb-0 text-white-50">
                Add, edit, publish and manage customer testimonials.
            </p>
        </div>

        <a href="dashboard.php" target="_blank" rel="noopener"
           class="btn btn-light mt-3 mt-md-0">
            <i class="bi bi-box-arrow-up-right me-1"></i> Back To Dashboard
        </a>
    </div>

    <!-- Flash Message -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show"
             role="alert">
            <?= e($message) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-chat-left-text"></i></div>
                <div class="stat-number"><?= $totalReviews ?></div>
                <div class="text-secondary">Total Reviews</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-icon text-success bg-success-subtle">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-number"><?= $activeReviews ?></div>
                <div class="text-secondary">Published Reviews</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-icon text-danger bg-danger-subtle">
                    <i class="bi bi-eye-slash"></i>
                </div>
                <div class="stat-number"><?= $inactiveReviews ?></div>
                <div class="text-secondary">Inactive Reviews</div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Form -->
    <div class="content-card" id="reviewForm">
        <h5 class="card-heading">
            <i class="bi bi-pencil-square me-2"></i>
            <?= (int)$editReview['id'] > 0 ? 'Edit Customer Review' : 'Add New Customer Review' ?>
        </h5>

        <form method="POST" action="manage_review.php">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['review_csrf_token']) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$editReview['id'] ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="customer_name" class="form-label">Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name"
                           class="form-control" maxlength="150"
                           value="<?= e($editReview['customer_name']) ?>"
                           placeholder="Enter customer name" required>
                </div>

                <div class="col-md-6">
                    <label for="event_type" class="form-label">Event Type *</label>
                    <input type="text" id="event_type" name="event_type"
                           class="form-control" maxlength="150"
                           value="<?= e($editReview['event_type']) ?>"
                           placeholder="e.g. Wedding Celebration" required>
                </div>

                <div class="col-md-6">
                    <label for="rating" class="form-label">Customer Rating *</label>
                    <select name="rating" id="rating" class="form-select" required>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>"
                                <?= (int)$editReview['rating'] === $i ? 'selected' : '' ?>>
                                <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                                <?= str_repeat('★', $i) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="sort_order" class="form-label">Display Order</label>
                    <input type="number" id="sort_order" name="sort_order"
                           class="form-control" min="0"
                           value="<?= (int)$editReview['sort_order'] ?>" required>
                    <small class="text-secondary">Smaller numbers appear first.</small>
                </div>

                <div class="col-12">
                    <label for="review_text" class="form-label">Review Message *</label>
                    <textarea id="review_text" name="review_text"
                              class="form-control" rows="4"
                              placeholder="Enter the customer's review..."
                              required><?= e($editReview['review_text']) ?></textarea>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i>
                        <?= (int)$editReview['id'] > 0 ? 'Update Review' : 'Add Review' ?>
                    </button>

                    <?php if ((int)$editReview['id'] > 0): ?>
                        <a href="manage_review.php" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-x-circle me-1"></i> Cancel Edit
                        </a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Reviews Table -->
    <div class="content-card">
        <div class="d-flex flex-column flex-md-row justify-content-between
                    align-items-md-center gap-3 mb-4">
            <h5 class="card-heading mb-0">
                <i class="bi bi-list-ul me-2"></i>All Customer Reviews
            </h5>

            <form method="GET" action="manage_review.php"
                  class="d-flex gap-2">
                <input type="search" name="search" class="form-control"
                       placeholder="Search reviews..."
                       value="<?= e($search) ?>">
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-search"></i>
                </button>

                <?php if ($search !== ''): ?>
                    <a href="manage_review.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Event</th>
                    <th>Review</th>
                    <th>Rating</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($reviewsResult->num_rows > 0): ?>
                    <?php while ($review = $reviewsResult->fetch_assoc()): ?>
                        <?php
                        $name = $review['customer_name'];
                        $initial = function_exists('mb_substr')
                            ? mb_substr($name, 0, 1, 'UTF-8')
                            : substr($name, 0, 1);
                        $ratingValue = max(1, min(5, (int)$review['rating']));
                        ?>
                        <tr>
                            <td><?= (int)$review['id'] ?></td>

                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="customer-avatar">
                                        <?= e($initial) ?>
                                    </span>
                                    <strong><?= e($name) ?></strong>
                                </div>
                            </td>

                            <td><?= e($review['event_type']) ?></td>

                            <td class="review-preview">
                                <?= e($review['review_text']) ?>
                            </td>

                            <td>
                                <span class="stars" title="<?= $ratingValue ?> out of 5 stars">
                                    <?= str_repeat('★', $ratingValue) ?>
                                    <span class="text-secondary">
                                        <?= str_repeat('☆', 5 - $ratingValue) ?>
                                    </span>
                                </span>
                            </td>

                            <td><?= (int)$review['sort_order'] ?></td>

                            <td>
                                <?php if ((int)$review['status'] === 1): ?>
                                    <span class="badge bg-success-subtle text-success">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-buttons">
                                    <a href="manage_review.php?edit=<?= (int)$review['id'] ?>#reviewForm"
                                       class="btn btn-sm btn-primary"
                                       title="Edit review">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <form method="POST" action="manage_review.php">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['review_csrf_token']) ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$review['id'] ?>">

                                        <button type="submit"
                                                class="btn btn-sm <?= (int)$review['status'] === 1 ? 'btn-warning' : 'btn-success' ?>"
                                                title="<?= (int)$review['status'] === 1 ? 'Deactivate review' : 'Activate review' ?>">
                                            <i class="bi <?= (int)$review['status'] === 1 ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="manage_review.php"
                                          onsubmit="return confirm('Are you sure you want to delete this review? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['review_csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$review['id'] ?>">

                                        <button type="submit"
                                                class="btn btn-sm btn-danger"
                                                title="Delete review">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-chat-square-text fs-1 text-secondary"></i>
                            <p class="mt-2 mb-0">No customer reviews found.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center text-secondary small py-2">
        Customer Reviews Management &copy; <?= date('Y') ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$stmt->close();
?>
