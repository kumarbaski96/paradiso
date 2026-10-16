
<?php

session_start();

// Prevent dashboard pages from being cached by the browser.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Check login session.
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

include'config/db.php';

// If you already have an admin login system, enable this check
// after confirming your login code sets $_SESSION['admin_id'].
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: admin_login.php');
//     exit;
// }

// Dashboard statistics
$totalBanners = 0;
$activeBanners = 0;
$inactiveBanners = 0;

$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 1), 0) AS active,
        COALESCE(SUM(status = 0), 0) AS inactive
    FROM banners
")->fetch_assoc();

$totalBanners = (int)$stats['total'];
$activeBanners = (int)$stats['active'];
$inactiveBanners = (int)$stats['inactive'];

// Latest banners
$recentBanners = $conn->query("
    SELECT id, banner_title, banner_image, status, sort_order
    FROM banners
    ORDER BY id DESC
    LIMIT 5
");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Admin Dashboard |Paradiso The Event</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

   <link rel="stylesheet" href="css/dashboard.css">
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">
        <i class="bi bi-gem me-2"></i>
        Wedding Hall
        <div class="small text-white-50 mt-2"
             style="font-size:12px;font-weight:400;">
            ADMINISTRATION PANEL
        </div>
    </div>

   <nav>
    <a href="dashboard.php" class="nav-link active">
        <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <a href="manage_admin_info.php" class="nav-link">
        <i class="bi bi-person-vcard"></i> Manage Admin Info
    </a>

    <a href="manage_banner.php" class="nav-link">
        <i class="bi bi-images"></i> Manage Banners
    </a>

    <a href="manage_about.php" class="nav-link">
        <i class="bi bi-info-circle"></i> Manage About
    </a>

    <a href="manage_services.php" class="nav-link">
        <i class="bi bi-tools"></i> Manage Services
    </a>

    <a href="manage_gallery.php" class="nav-link">
        <i class="bi bi-image"></i> Manage Gallery
    </a>

    <a href="manage_review.php" class="nav-link">
        <i class="bi bi-star"></i> Manage Review
    </a>

    <a href="manage_booking.php" class="nav-link">
        <i class="bi bi-calendar-check"></i> Manage Booking
    </a>

    <a href="manage_logo.php" class="nav-link">
        <i class="bi bi-badge-tm"></i> Manage Logo
    </a>

    <a href="manage_social.php" class="nav-link">
        <i class="bi bi-share"></i> Manage Social Networking
    </a>

    <a href="index.php" target="_blank" class="nav-link">
        <i class="bi bi-globe"></i> View Website
    </a>

    <a href="logout.php" class="nav-link">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</nav>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

    <div class="topbar">
        <div>
            <h4 class="mb-0 fw-bold">Admin Dashboard</h4>
            <div class="welcome">
                Welcome to your wedding hall management system.
            </div>
        </div>

        <div class="text-end">
            <i class="bi bi-person-circle fs-3 text-secondary"></i>
            <div class="small text-muted">Administrator</div>
        </div>
    </div>

    <!-- STATISTICS -->
    <div class="row g-4">

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <div class="stat-icon"
                         style="background:#e8efff;color:#3867d6;">
                        <i class="bi bi-images"></i>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">
                        All banners
                    </span>
                </div>

                <div class="stat-number">
                    <?= $totalBanners ?>
                </div>

                <div class="stat-label">Total Banners</div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <div class="stat-icon"
                         style="background:#e2f8ed;color:#198754;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <span class="badge bg-success-subtle text-success">
                        Visible
                    </span>
                </div>

                <div class="stat-number">
                    <?= $activeBanners ?>
                </div>

                <div class="stat-label">Active Banners</div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <div class="stat-icon"
                         style="background:#fff0df;color:#d97706;">
                        <i class="bi bi-eye-slash"></i>
                    </div>
                    <span class="badge bg-warning-subtle text-warning">
                        Hidden
                    </span>
                </div>

                <div class="stat-number">
                    <?= $inactiveBanners ?>
                </div>

                <div class="stat-label">Inactive Banners</div>
            </div>
        </div>

    </div>

    <!-- QUICK ACTIONS -->
    <div class="content-card">
        <h5 class="fw-bold mb-3">Quick Actions</h5>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <a href="banner_admin.php"
                   class="btn btn-primary w-100 py-3">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add or Manage Banners
                </a>
            </div>

            <div class="col-12 col-md-6">
                <a href="index.php" target="_blank"
                   class="btn btn-outline-dark w-100 py-3">
                    <i class="bi bi-box-arrow-up-right me-2"></i>
                    Preview Website
                </a>
            </div>
        </div>
    </div>

    <!-- RECENT BANNERS -->
    <div class="content-card">
        <div class="d-flex justify-content-between
                    align-items-center flex-wrap gap-2 mb-3">

            <h5 class="fw-bold mb-0">Recent Banners</h5>

            <a href="banner_admin.php"
               class="btn btn-sm btn-outline-primary">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Banner Title</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($recentBanners->num_rows > 0): ?>

                    <?php while ($banner = $recentBanners->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$banner['id'] ?></td>

                            <td>
                                <img
                                    src="<?= e($banner['banner_image']) ?>"
                                    class="banner-image"
                                    alt="Banner image">
                            </td>

                            <td>
                                <?= e(strip_tags($banner['banner_title'])) ?>
                            </td>

                            <td><?= (int)$banner['sort_order'] ?></td>

                            <td>
                                <?php if ((int)$banner['status'] === 1): ?>
                                    <span class="badge bg-success">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a href="banner_admin.php?edit=<?= (int)$banner['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No banners found. Add your first banner.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-center text-muted small mt-4">
        Wedding Hall Admin Panel &copy; <?= date('Y') ?>
    </p>

</main>

</body>
</html>
