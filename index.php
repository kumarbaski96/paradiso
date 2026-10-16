<?php include 'config/db.php'?>
<!DOCTYPE html>
<html lang="en">
<?php include 'top_application.php'?>
<body>



<?php include 'header.php'?>

<!-- FIVE-SLIDE HERO -->
<section class="hero" id="home">
    <div id="weddingSlider" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="4500" data-bs-pause="false">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#weddingSlider" data-bs-slide-to="0" class="active" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#weddingSlider" data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#weddingSlider" data-bs-slide-to="2" aria-label="Slide 3"></button>
            <button type="button" data-bs-target="#weddingSlider" data-bs-slide-to="3" aria-label="Slide 4"></button>
            <button type="button" data-bs-target="#weddingSlider" data-bs-slide-to="4" aria-label="Slide 5"></button>
        </div>
       

<?php
require_once __DIR__ . '/config/db.php';

$bannerResult = $conn->query("
    SELECT id, banner_title, banner_subtitle, banner_description,
           banner_image, button_text, button_link
    FROM banners
    WHERE status = 1
    ORDER BY sort_order ASC, id DESC
");
?>

<div id="heroCarousel"
     class="carousel slide carousel-fade"
     data-bs-ride="carousel"
     data-bs-interval="4000"
     data-bs-pause="false">

    <div class="carousel-indicators">
        <?php
        $slideIndex = 0;
        while ($banner = $bannerResult->fetch_assoc()):
        ?>
            <button type="button"
                    data-bs-target="#heroCarousel"
                    data-bs-slide-to="<?= $slideIndex ?>"
                    class="<?= $slideIndex === 0 ? 'active' : '' ?>"
                    <?= $slideIndex === 0 ? 'aria-current="true"' : '' ?>
                    aria-label="Slide <?= $slideIndex + 1 ?>">
            </button>
        <?php
            $slideIndex++;
        endwhile;

        $bannerResult->data_seek(0);
        ?>
    </div>

    <div class="carousel-inner">
        <?php
        $firstSlide = true;

        while ($banner = $bannerResult->fetch_assoc()):
        ?>
            <div class="carousel-item <?= $firstSlide ? 'active' : '' ?>">
                <img
                    src="<?= htmlspecialchars($banner['banner_image'], ENT_QUOTES, 'UTF-8') ?>"
                    class="d-block w-100"
                    alt="<?= htmlspecialchars(strip_tags($banner['banner_title']), ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="hero-copy">
                    <div class="container">
                        <div class="hero-inner">

                            <?php if (!empty($banner['banner_subtitle'])): ?>
                                <div class="eyebrow">
                                    <?= htmlspecialchars($banner['banner_subtitle'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <h1>
                                <?= nl2br(htmlspecialchars(strip_tags($banner['banner_title']), ENT_QUOTES, 'UTF-8')) ?>
                            </h1>

                            <p>
                                <?= htmlspecialchars($banner['banner_description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <a href="<?= htmlspecialchars($banner['button_link'], ENT_QUOTES, 'UTF-8') ?>"
                               class="btn btn-gold">
                                <?= htmlspecialchars($banner['button_text'], ENT_QUOTES, 'UTF-8') ?>
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        <?php
            $firstSlide = false;
        endwhile;
        ?>
    </div>

    <button class="carousel-control-prev"
            type="button"
            data-bs-target="#heroCarousel"
            data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>

    <button class="carousel-control-next"
            type="button"
            data-bs-target="#heroCarousel"
            data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>


            
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#weddingSlider" data-bs-slide="prev" aria-label="Previous slide"><span class="carousel-control-prev-icon"></span></button>
        <button class="carousel-control-next" type="button" data-bs-target="#weddingSlider" data-bs-slide="next" aria-label="Next slide"><span class="carousel-control-next-icon"></span></button>
        <div class="hero-count" id="heroCount">01 <span class="text-warning">/</span> 05</div>
    </div>
</section>



<?php


$sql = "SELECT * FROM about WHERE status = 1 ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);
$about = $result ? $result->fetch_assoc() : null;

function aboutText($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!-- ABOUT SECTION -->
<?php if ($about): ?>
<section class="section" id="about">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- About Image -->
            <div class="col-lg-6">
                <div class="welcome-image position-relative">

                    <img
                        src="<?= aboutText($about['image']) ?>"
                        alt="<?= aboutText($about['image_alt']) ?>"
                        class="img-fluid w-100"
                        loading="lazy"
                    >

                    <div class="experience-stamp">
                        <strong>&hearts;</strong>
                        <span>
                            <?= aboutText($about['stamp_text']) ?>
                        </span>
                    </div>

                </div>
            </div>

            <!-- About Content -->
            <div class="col-lg-6">

                <div class="section-label">
                    <?= aboutText($about['section_label']) ?>
                </div>

                <h2 class="section-title mt-3">
                    <?= aboutText($about['heading']) ?>
                </h2>

                <div class="gold-line"></div>

                <p class="section-desc">
                    <?= nl2br(aboutText($about['description1'])) ?>
                </p>

                <p class="section-desc">
                    <?= nl2br(aboutText($about['description2'])) ?>
                </p>

                <!-- Dynamic Features -->
                <div class="row g-3 mt-3">

                    <?php
                    for ($i = 1; $i <= 4; $i++):
                        $feature = $about["feature$i"];
                    ?>

                    <div class="col-6">
                        <i class="bi bi-check-circle-fill text-warning me-2"></i>
                        <span class="small fw-bold">
                            <?= aboutText($feature) ?>
                        </span>
                    </div>

                    <?php endfor; ?>

                </div>

                <!-- Dynamic Button -->
                <a
                    href="<?= aboutText($about['button_link']) ?>"
                    class="btn btn-gold mt-4"
                >
                    <?= aboutText($about['button_text']) ?>
                    <i class="bi bi-arrow-right ms-2"></i>
                </a>

            </div>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- HIGHLIGHTS -->
<section class="feature-strip">
    <div class="container"><div class="row g-3">
        <div class="col-6 col-lg-3 feature-stat"><strong><i class="bi bi-heart-fill"></i></strong><span>Wedding Celebrations</span></div>
        <div class="col-6 col-lg-3 feature-stat"><strong><i class="bi bi-stars"></i></strong><span>Elegant Decoration</span></div>
        <div class="col-6 col-lg-3 feature-stat"><strong><i class="bi bi-people-fill"></i></strong><span>Family Gatherings</span></div>
        <div class="col-6 col-lg-3 feature-stat"><strong><i class="bi bi-calendar-heart"></i></strong><span>Special Memories</span></div>
    </div></div>
</section>

<!-- FACILITIES -->

<?php

$services = $conn->query(
    "SELECT id, service_title, service_description, service_icon
     FROM services
     WHERE status = 1
     ORDER BY sort_order ASC, id DESC"
);
?>

<!-- DYNAMIC FACILITIES & SERVICES -->
<section class="section section-cream" id="facilities">
    <div class="container">

        <div class="text-center mb-5">
            <div class="section-label">EVERY DETAIL MATTERS</div>

            <h2 class="section-title mt-3">
                Our Facilities & Services
            </h2>

            <div class="gold-line mx-auto"></div>

            <p class="section-desc mx-auto"
               style="max-width:620px">
                Thoughtful facilities to help make your wedding,
                reception or special celebration comfortable and memorable.
            </p>
        </div>

        <div class="row g-4">

            <?php if ($services && $services->num_rows > 0): ?>

                <?php while ($service = $services->fetch_assoc()): ?>

                    <div class="col-6 col-lg-3">
                        <div class="facility-card h-100">

                            <div class="facility-icon">
                                <i class="bi <?= htmlspecialchars(
                                    $service['service_icon'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"></i>
                            </div>

                            <h3>
                                <?= htmlspecialchars(
                                    $service['service_title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>

                            <p>
                                <?= nl2br(htmlspecialchars(
                                    $service['service_description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )) ?>
                            </p>

                        </div>
                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="col-12 text-center">
                    <p>No facilities or services are available at present.</p>
                </div>

            <?php endif; ?>

        </div>
    </div>
</section>


<!-- GALLERY -->
<section class="section" id="gallery">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-5">
            <div>
                <div class="section-label">A GLIMPSE OF CELEBRATION</div>
                <h2 class="section-title mt-3 mb-0">Our Photo Gallery</h2>
                <div class="gold-line"></div>
                <p class="section-desc mb-0">Beautiful settings. Precious moments. Lasting memories.</p>
            </div>
            <a href="#booking" class="btn btn-gold">CELEBRATE WITH US <i class="bi bi-arrow-right ms-2"></i></a>
        </div>
        <div class="row g-3">
          
<?php
// Fetch active gallery records
$sql = "SELECT id, title, category, image
        FROM gallery
        WHERE status = 1
        ORDER BY sort_order ASC, id DESC";

$result = $conn->query($sql);
?>

<?php if ($result && $result->num_rows > 0): ?>

    <?php while ($row = $result->fetch_assoc()): ?>

        <div class="col-6 col-lg-3">

            <a href="<?= htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8') ?>"
               class="gallery-card d-block"
               data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>">

                <img
                    src="<?= htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>"
                    loading="lazy"
                >

                <div class="gallery-caption">

                    <span>
                        <?= htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <h3>
                        <?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                </div>

            </a>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="col-12 text-center">
        <p>No gallery images available.</p>
    </div>

<?php endif; ?>

        </div>
    </div>
</section>

<!-- REVIEWS SLIDER -->
<section class="section section-cream" id="reviews">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">KIND WORDS</div>
            <h2 class="section-title mt-3">Visitor Reviews</h2>
            <div class="gold-line mx-auto"></div>
            <p class="section-desc">Every celebration is special, and every memory matters.</p>
        </div>
       
<?php
// Fetch active reviews in the configured display order
$sql = "SELECT id, customer_name, event_type, review_text,
               rating, sort_order
        FROM reviews
        WHERE status = 1
        ORDER BY sort_order ASC, id DESC";

$result = $conn->query($sql);

$reviews = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// Group reviews into slides of three cards each
$reviewSlides = array_chunk($reviews, 3);
?>

<div id="reviewSlider"
     class="carousel slide"
     data-bs-ride="carousel"
     data-bs-interval="5500">

    <div class="carousel-inner">

        <?php if (!empty($reviewSlides)): ?>

            <?php foreach ($reviewSlides as $slideIndex => $slideReviews): ?>

                <div class="carousel-item <?= $slideIndex === 0 ? 'active' : '' ?>">

                    <div class="row g-4">

                        <?php foreach ($slideReviews as $review): ?>

                            <?php
                            $customerName = $review['customer_name'];
                            $rating = max(1, min(5, (int)$review['rating']));

                            $initial = function_exists('mb_substr')
                                ? mb_substr($customerName, 0, 1, 'UTF-8')
                                : substr($customerName, 0, 1);
                            ?>

                            <div class="col-md-4">

                                <article class="review-card">

                                    <div class="d-flex justify-content-between">
                                        <span class="review-quote">
                                            <i class="bi bi-quote"></i>
                                        </span>

                                        <span class="review-stars"
                                              aria-label="<?= $rating ?> out of 5 stars">
                                            <?= str_repeat('★', $rating) ?><span class="text-secondary"><?= str_repeat('☆', 5 - $rating) ?></span>
                                        </span>
                                    </div>

                                    <p class="review-text mt-3">
                                        <?= htmlspecialchars(
                                            $review['review_text'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <div class="review-person">

                                        <div class="review-avatar">
                                            <?= htmlspecialchars(
                                                $initial,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <div>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $customerName,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </strong>

                                            <small>
                                                <?= htmlspecialchars(
                                                    $review['event_type'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </small>
                                        </div>

                                    </div>

                                </article>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="carousel-item active">
                <div class="text-center py-5">
                    <p>No customer reviews are available yet.</p>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <?php if (count($reviewSlides) > 1): ?>

        <div class="d-flex justify-content-center gap-3 mt-4">

            <button class="btn btn-outline-secondary rounded-circle"
                    type="button"
                    data-bs-target="#reviewSlider"
                    data-bs-slide="prev"
                    aria-label="Previous reviews">
                <i class="bi bi-arrow-left"></i>
            </button>

            <button class="btn btn-outline-secondary rounded-circle"
                    type="button"
                    data-bs-target="#reviewSlider"
                    data-bs-slide="next"
                    aria-label="Next reviews">
                <i class="bi bi-arrow-right"></i>
            </button>

        </div>

    <?php endif; ?>

</div>

        <p class="text-center text-secondary mt-4 small">Demo reviews only. Replace these with genuine visitor feedback before publishing.</p>
    </div>
</section>

<!-- CONTACT + STATIC BOOKING FORM -->
<section class="section booking-section" id="booking">
    <div class="container position-relative">
        <div class="row g-5 align-items-center">
            <!-- <div class="col-lg-5 text-white" id="contact">
                <div class="section-label">LET'S MAKE IT SPECIAL</div>
                <h2 class="text-white mt-3" style="font-size:clamp(35px,4vw,48px)">Your Perfect Day Starts Here</h2>
                <div class="gold-line"></div>
                <p style="color:#dfcbd1;line-height:1.9;font-size:14px">Tell us about your event. Contact us directly or use the enquiry form to prepare your booking request.</p>
                <div class="mt-5">
                    <div class="contact-item"><i class="bi bi-geo-alt"></i><div><strong>OUR LOCATION</strong><span>Jharia, Dhanbad, Jharkhand, India</span></div></div>
                    <div class="contact-item"><i class="bi bi-telephone"></i><div><strong>CALL US</strong><a href="tel:+919876543210">+91 98765 43210</a></div></div>
                    <div class="contact-item"><i class="bi bi-envelope"></i><div><strong>EMAIL US</strong><a href="mailto:info@royalmarriagehall.com">info@royalmarriagehall.com</a></div></div>
                    <a class="btn btn-outline-gold mt-2" href="https://wa.me/919876543210?text=Hello%2C%20I%20would%20like%20to%20enquire%20about%20your%20marriage%20hall." target="_blank" rel="noopener"><i class="bi bi-whatsapp me-2"></i>CHAT ON WHATSAPP</a>
                </div>
            </div> -->
      


<?php

$successMessage = $_SESSION['booking_success'] ?? '';
$errorMessage = $_SESSION['booking_error'] ?? '';

unset($_SESSION['booking_success'], $_SESSION['booking_error']);
?>

<div class="container booking-wrapper" id="contact">
    <div class="booking-card">

        <h2 class="booking-title text-center mb-2">
            Marriage Hall Booking
        </h2>

        <p class="text-center text-muted mb-4">
            Fill in your details to enquire about your event.
        </p>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="booking_process.php" method="POST">

            <div class="row g-3">

                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name *</label>
                    <input type="text" id="name" name="name"
                           class="form-control" maxlength="100"
                           autocomplete="name" required>
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label">Mobile Number *</label>
                    <input type="tel" id="phone" name="phone"
                           class="form-control" maxlength="20"
                           autocomplete="tel" required>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" id="email" name="email"
                           class="form-control" maxlength="150"
                           autocomplete="email" required>
                </div>

                <div class="col-md-6">
                    <label for="event" class="form-label">Event Type *</label>
                    <select id="event" name="event" class="form-select" required>
                        <option value="">Select event</option>
                        <option value="Wedding">Wedding</option>
                        <option value="Engagement">Engagement</option>
                        <option value="Reception">Reception</option>
                        <option value="Birthday Party">Birthday Party</option>
                        <option value="Anniversary">Anniversary</option>
                        <option value="Corporate Event">Corporate Event</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="start_date" class="form-label">Start Date *</label>
                    <input type="date" id="start_date" name="start_date"
                           class="form-control" min="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label for="end_date" class="form-label">End Date *</label>
                    <input type="date" id="end_date" name="end_date"
                           class="form-control" min="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label for="total_days" class="form-label">
                        Total Booking Days
                    </label>
                    <input type="number" id="total_days"
                           class="form-control" value="1" readonly>
                    <small class="text-muted">
                        Start date and end date are both included.
                    </small>
                </div>

                <div class="col-md-6">
                    <label for="guests" class="form-label">
                        Expected Guests *
                    </label>
                    <input type="number" id="guests" name="guests"
                           class="form-control" min="1" max="100000"
                           required>
                </div>

                <div class="col-12">
                    <label for="message" class="form-label">
                        Additional Requirements
                    </label>
                    <textarea id="message" name="message"
                              class="form-control" maxlength="3000"
                              placeholder="Decoration, catering, or other requirements"></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" name="submit_booking" value="1"
                            class="btn btn-book w-100">
                        Submit Booking Enquiry
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<script>
const startDate = document.getElementById('start_date');
const endDate = document.getElementById('end_date');
const totalDays = document.getElementById('total_days');

function updateBookingDays() {
    if (!startDate.value) {
        endDate.min = "<?= date('Y-m-d') ?>";
        totalDays.value = 1;
        return;
    }

    endDate.min = startDate.value;

    if (endDate.value && endDate.value < startDate.value) {
        endDate.value = startDate.value;
    }

    if (endDate.value) {
        const start = new Date(startDate.value + 'T00:00:00');
        const end = new Date(endDate.value + 'T00:00:00');
        const days = Math.round((end - start) / 86400000) + 1;
        totalDays.value = days > 0 ? days : 1;
    } else {
        totalDays.value = 1;
    }
}

startDate.addEventListener('change', updateBookingDays);
endDate.addEventListener('change', updateBookingDays);
</script>

</div></div></section>


           
        </div>
    </div>
</section>

<?php include'footer.php'?>
<script>

    document.addEventListener("DOMContentLoaded", function () {
    const startDate = document.getElementById("startDate");
    const endDate = document.getElementById("endDate");
    const totalDays = document.getElementById("totalDays");

    if (!startDate || !endDate || !totalDays) return;

    function calculateEventDays() {
        const start = startDate.value;
        const end = endDate.value;

        if (!start || !end) {
            totalDays.textContent = "Select your dates";
            return;
        }

        const startTime = new Date(start + "T00:00:00Z").getTime();
        const endTime = new Date(end + "T00:00:00Z").getTime();

        if (endTime < startTime) {
            totalDays.textContent = "End date must be after start date";
            return;
        }

        const days = Math.round(
            (endTime - startTime) / (1000 * 60 * 60 * 24)
        ) + 1;

        totalDays.textContent = days + (days === 1 ? " Day" : " Days");
    }

    startDate.addEventListener("input", calculateEventDays);
    endDate.addEventListener("input", calculateEventDays);

    startDate.addEventListener("change", calculateEventDays);
    endDate.addEventListener("change", calculateEventDays);
});
</script>
</body>
</html>