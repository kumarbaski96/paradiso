
<!-- TOP BAR -->
<?php
include_once 'config/db.php';

$sql = "SELECT * FROM admins LIMIT 1";
$result = $conn->query($sql);

$admin = ($result && $result->num_rows > 0)
    ? $result->fetch_assoc()
    : null;
?>

<?php if ($admin): ?>
    <?php
    $address = trim($admin['address'] ?? '');
    $email   = trim($admin['email'] ?? '');
     $mobile   = trim($admin['mobile1'] ?? '');
    ?>

    <?php if ($address !== '' || $email !== ''): ?>
        <div class="topline">
            <div class="container d-flex justify-content-between align-items-center">

                <?php if ($address !== ''): ?>
                    <div>
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if ($email !== ''): ?>
                    <div class="top-right">
                        <a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi bi-envelope me-1"></i>
                            <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
                            <i class="bi bi-phone me-1"></i>
                            <?= htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<!-- HEADER -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container py-2">
 <?php
 
require_once __DIR__ . '/config/db.php';

// Default logo
$logoPath = 'img/logo.jpeg';

// Fetch the active logo from the database
$sql = "SELECT logo FROM `logo`
        WHERE status = 1
        ORDER BY id DESC
        LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    if (!empty($row['logo'])) {
        $logoPath = trim($row['logo']);
    }
}
?>

<a class="navbar-brand" href="index.php">
    <img
        src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>"
        alt="Paradiso The Venue Logo"
        class="site-logo"
        onerror="this.onerror=null; this.src='img/logo.jpeg';">
</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="#home">HOME</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">ABOUT</a></li>
                <li class="nav-item"><a class="nav-link" href="#facilities">FACILITIES</a></li>
                <li class="nav-item"><a class="nav-link" href="#gallery">GALLERY</a></li>
                <li class="nav-item"><a class="nav-link" href="#reviews">REVIEWS</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">CONTACT</a></li>
                <li class="nav-item ms-lg-3 mt-3 mt-lg-0"><a class="btn btn-gold" href="#booking">BOOK YOUR DATE <i class="bi bi-arrow-up-right ms-1"></i></a></li>
                <li class="nav-item ms-lg-3 mt-3 mt-lg-0">
    <a class="btn btn-danger"
       href="login.php"
       target="_blank"
       rel="noopener noreferrer">
        Admin Login <i class="bi bi-arrow-up-right ms-1"></i>
    </a>
</li>
                <!-- <li class="nav-item"><a class="nav-link" href="login.php">Admin Login</a></li> -->
            </ul>
        </div>
    </div>
</nav>