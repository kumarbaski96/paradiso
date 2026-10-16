<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4 col-md-6">
                <a class="d-flex align-items-center gap-2 mb-4" href="#home">
                    <span class="brand-mark" style="background:#fffaf3"><i class="bi bi-flower2"></i></span>
                    <span><span class="brand-name d-block" style="color:white">Royal Marriage Hall</span><span class="brand-caption" style="color:#d5ad68">WEDDINGS · EVENTS · MEMORIES</span></span>
                </a>
                <p>A special place for life's most meaningful celebrations. Let us help you make beautiful memories with family and friends.</p>
               <?php

$sql = "SELECT platform_name, social_url, icon_class
        FROM social_networking
        WHERE status = 1
        ORDER BY id ASC";

$result = $conn->query($sql);
?>

<div class="mt-4">
    <?php if ($result && $result->num_rows > 0): ?>

        <?php while ($social = $result->fetch_assoc()): ?>
            <?php
            $platform = trim($social['platform_name'] ?? '');
            $url      = trim($social['social_url'] ?? '');
            $icon     = trim($social['icon_class'] ?? '');

            // Allow only HTTP/HTTPS external links.
            $scheme = strtolower(
                parse_url($url, PHP_URL_SCHEME) ?? ''
            );

            if (
                $platform === '' ||
                $url === '' ||
                !in_array($scheme, ['http', 'https'], true)
            ) {
                continue;
            }
            ?>

            <a
                class="social-link"
                href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="<?= htmlspecialchars($platform, ENT_QUOTES, 'UTF-8') ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
            </a>

        <?php endwhile; ?>

    <?php endif; ?>
</div>
            </div>
            <div class="col-lg-2 col-md-6">
                <h3 class="footer-title">Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li><li><a href="#about">About Us</a></li><li><a href="#facilities">Facilities</a></li><li><a href="#gallery">Gallery</a></li><li><a href="#reviews">Reviews</a></li><li><a href="#booking">Booking Enquiry</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h3 class="footer-title">Our Services</h3>
                <ul class="footer-links">
                    <li><a href="#facilities">Wedding Ceremony</a></li><li><a href="#facilities">Reception Party</a></li><li><a href="#facilities">Engagement Ceremony</a></li><li><a href="#facilities">Birthday Celebration</a></li><li><a href="#facilities">Family Functions</a></li>
                </ul>
            </div>
            <?php
include_once 'config/db.php';

$sql = "SELECT * FROM admins LIMIT 1";
$result = $conn->query($sql);

$admin = ($result && $result->num_rows > 0)
    ? $result->fetch_assoc()
    : null;

$address = trim($admin['address'] ?? '');
$phone   = trim($admin['mobile1'] ?? '');
$email   = trim($admin['email'] ?? '');
$zip   = trim($admin['pin_code'] ?? '');
?>

<div class="col-lg-3 col-md-6">
    <h3 class="footer-title">Get in Touch</h3>

    <?php if ($address !== ''): ?>
        <p>
            <i class="bi bi-geo-alt me-2 text-warning"></i>
            <?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>,<?= htmlspecialchars($zip, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($phone !== ''): ?>
        <p>
            <i class="bi bi-telephone me-2 text-warning"></i>
            <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($email !== ''): ?>
        <p>
            <i class="bi bi-envelope me-2 text-warning"></i>
            <a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </p>
    <?php endif; ?>

    <a href="#booking" class="btn btn-gold mt-2">ENQUIRE NOW</a>
</div>
        </div>
        <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; <span id="year"></span> Royal Marriage Hall. All rights reserved.</span>
            <span>Designed for beautiful beginnings <i class="bi bi-heart-fill text-warning"></i></span>
        </div>
    </div>
</footer>

<!-- GALLERY IMAGE POPUP -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0"><h5 class="modal-title text-white" id="galleryModalTitle"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body text-center pt-0"><img id="galleryModalImage" src="" alt="" class="img-fluid" style="max-height:78vh;object-fit:contain"></div>
        </div>
    </div>
</div>

<a class="whatsapp-float" href="https://wa.me/919876543210?text=Hello%2C%20I%20would%20like%20to%20enquire%20about%20your%20marriage%20hall." target="_blank" rel="noopener" aria-label="Contact us on WhatsApp"><i class="bi bi-whatsapp"></i></a>
<a href="#home" class="back-top" id="backTop" aria-label="Back to top"><i class="bi bi-arrow-up"></i></a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>