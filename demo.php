<?php
require_once 'config/db.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Default hall details
$hall = [
    'hall_name' => 'Paradiso The Venue',
    'tagline' => 'Where Your Forever Begins',
    'phone' => '9876543210',
    'email' => 'info@royalmarriagehall.com',
    'address' => 'Govindhpur, Dhanbad, Jharkhand, India',
    'whatsapp_number' => '919876543210'
];

// Load website settings
if (isset($conn) && $conn instanceof mysqli) {
    $result = $conn->query("SELECT * FROM site_settings ORDER BY id ASC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $hall = array_merge($hall, $result->fetch_assoc());
    }
}

// Load gallery images
$gallery = [];
if (isset($conn) && $conn instanceof mysqli) {
    $result = $conn->query(
        "SELECT title, category, image_path, caption
         FROM gallery_images
         WHERE status = 1
         ORDER BY display_order ASC, id DESC
         LIMIT 8"
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $gallery[] = $row;
        }
    }
}

// Load facilities
$facilities = [];
if (isset($conn) && $conn instanceof mysqli) {
    $result = $conn->query(
        "SELECT facility_name, description, icon
         FROM facilities
         WHERE status = 1
         ORDER BY display_order ASC
         LIMIT 8"
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $facilities[] = $row;
        }
    }
}

// Handle booking enquiry
$bookingMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_booking'])) {

    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $event = trim($_POST['event_type'] ?? '');
    $date = trim($_POST['event_date'] ?? '');
    $guests = (int)($_POST['guest_count'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    $validDate = DateTime::createFromFormat('!Y-m-d', $date);
    $dateValid = $validDate && $validDate->format('Y-m-d') === $date;

    if ($name === '' || $phone === '' || $event === ''
        || !$dateValid || $date < date('Y-m-d')) {
        $bookingMessage = 'Please enter valid details and choose a future event date.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $bookingMessage = 'Please enter a valid email address.';
    } elseif ($guests < 1 || $guests > 100000) {
        $bookingMessage = 'Please enter a valid number of guests.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO bookings
            (customer_name, email, phone, event_type, event_date,
             guest_count, hall_name, message, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
        );

        if ($stmt) {
            $hallName = $hall['hall_name'];
            $stmt->bind_param(
                "sssssiss",
                $name, $email, $phone, $event, $date,
                $guests, $hallName, $message
            );

            if ($stmt->execute()) {
                $bookingMessage = 'Thank you! Your booking enquiry has been submitted.';
                $_POST = [];
            } else {
                $bookingMessage = 'Unable to submit your enquiry. Please try again.';
            }
            $stmt->close();
        } else {
            $bookingMessage = 'Unable to process your enquiry right now.';
        }
    }
}

// Five banner images
$banners = [
    [
        'image' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=2000&q=85',
        'tag' => 'A GRAND CELEBRATION',
        'title' => 'Your Dream Wedding',
        'subtitle' => 'A beautiful beginning to your forever story.'
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=2000&q=85',
        'tag' => 'ELEGANCE IN EVERY DETAIL',
        'title' => 'Celebrate in Style',
        'subtitle' => 'Beautiful spaces for your most precious moments.'
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=2000&q=85',
        'tag' => 'MEMORIES THAT LAST',
        'title' => 'Moments of Magic',
        'subtitle' => 'Turn your special occasion into an unforgettable memory.'
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=2000&q=85',
        'tag' => 'YOUR PERFECT VENUE',
        'title' => 'A Royal Experience',
        'subtitle' => 'Celebrate love, family and togetherness with us.'
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=2000&q=85&sat=-20',
        'tag' => 'MAKE IT UNFORGETTABLE',
        'title' => 'The Celebration Begins Here',
        'subtitle' => 'Your occasion deserves a venue as special as you are.'
    ]
];

$reviews = [
    [
        'name' => 'Amit Kumar',
        'event' => 'Wedding Celebration',
        'stars' => 5,
        'review' => 'A lovely venue for our family celebration. The ambience and arrangements made our special day memorable.'
    ],
    [
        'name' => 'Priya Sharma',
        'event' => 'Wedding Reception',
        'stars' => 5,
        'review' => 'Beautiful surroundings and a welcoming atmosphere. It was a wonderful place to celebrate with family.'
    ],
    [
        'name' => 'Rahul Singh',
        'event' => 'Engagement Ceremony',
        'stars' => 5,
        'review' => 'We enjoyed celebrating our engagement here. The venue gave our event a very special feeling.'
    ]
];

$icons = [
    'Spacious Marriage Hall' => 'bi-building',
    'Wedding Decoration' => 'bi-flower1',
    'Catering Services' => 'bi-cup-hot',
    'Parking Facility' => 'bi-car-front',
    'Lighting and Sound' => 'bi-speaker',
    'Event Assistance' => 'bi-calendar-heart',
    'Guest Seating' => 'bi-people',
    'Photography Space' => 'bi-camera'
];

$phone = preg_replace('/[^0-9+]/', '', $hall['phone'] ?? '');
$whatsapp = preg_replace('/[^0-9]/', '', $hall['whatsapp_number'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= h($hall['hall_name']) ?> - a beautiful venue for weddings, receptions and special occasions in Dhanbad, Jharkhand.">
<title><?= h($hall['hall_name']) ?> | Wedding & Event Venue</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --wine: #65172d;
    --wine-dark: #360d1c;
    --gold: #d5ad68;
    --cream: #fffaf3;
    --ink: #302329;
    --muted: #81757a;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; scroll-padding-top: 85px; }
body {
    margin: 0;
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
    background: #fff;
}
h1,h2,h3,h4,.serif { font-family: 'Playfair Display', serif; }
a { text-decoration: none; }
.topline {
    background: var(--wine-dark);
    color: #f8e9d3;
    padding: 9px 0;
    font-size: 12px;
}
.topline a { color: #f8e9d3; }
.navbar {
    background: rgba(255,250,243,.98);
    box-shadow: 0 5px 22px #2d10200d;
}
.brand-mark {
    width: 44px;
    height: 44px;
    border: 1px solid var(--gold);
    border-radius: 50%;
    color: var(--wine);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.brand-name { color: var(--wine); font-family: 'Playfair Display',serif; font-weight: 700; line-height: 1.1; }
.brand-caption { color: #8e7867; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; }
.nav-link { color: #49363d; font-size: 13px; font-weight: 700; margin: 0 5px; }
.nav-link:hover { color: var(--wine); }
.btn-gold {
    background: var(--gold);
    border: 1px solid var(--gold);
    color: #35131e;
    border-radius: 3px;
    padding: 12px 22px;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: .5px;
    transition: .25s;
}
.btn-gold:hover { background: #e6c58e; border-color: #e6c58e; color: #35131e; transform: translateY(-2px); }
.btn-outline-gold {
    border: 1px solid var(--gold);
    color: white;
    padding: 12px 22px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 700;
}
.btn-outline-gold:hover { background: var(--gold); color: var(--wine-dark); }
.hero { position: relative; background: var(--wine-dark); }
.hero .carousel-item { height: min(690px, 78vh); min-height: 480px; position: relative; }
.hero .carousel-item img { width: 100%; height: 100%; object-fit: cover; }
.hero .carousel-item::after {
    content: ""; position: absolute; inset: 0;
    background: linear-gradient(90deg,rgba(30,7,17,.83),rgba(30,7,17,.38) 58%,rgba(30,7,17,.18)),
                linear-gradient(0deg,rgba(30,7,17,.45),transparent 40%);
}
.hero-copy {
    position: absolute; z-index: 2; inset: 0;
    display: flex; align-items: center;
    color: white;
}
.hero-inner { max-width: 790px; }
.eyebrow {
    color: var(--gold); letter-spacing: 4px; font-size: 11px;
    font-weight: 700; text-transform: uppercase;
}
.hero h1 { font-size: clamp(42px,6vw,76px); line-height: 1.08; margin: 22px 0; }
.hero p { color: #f5e9e9; max-width: 530px; font-size: 16px; line-height: 1.9; }
.hero .carousel-indicators { z-index: 3; margin-bottom: 28px; }
.hero .carousel-indicators [data-bs-target] { width: 26px; height: 4px; border: 0; }
.hero .carousel-control-prev,.hero .carousel-control-next { z-index: 3; width: 8%; }
.hero-count {
    position: absolute; z-index: 3; right: 7%; bottom: 28px;
    color: white; font-size: 12px; letter-spacing: 3px;
}
.section { padding: 90px 0; }
.section-cream { background: var(--cream); }
.section-label { color: #a47a3e; letter-spacing: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.section-title { font-size: clamp(32px,4vw,47px); color: var(--wine); line-height: 1.2; }
.section-desc { color: var(--muted); font-size: 14px; line-height: 1.9; }
.gold-line { width: 58px; height: 2px; background: var(--gold); margin: 20px 0; }
.welcome-image { position: relative; padding: 0 25px 25px 0; }
.welcome-image img { width: 100%; height: 430px; object-fit: cover; }
.welcome-image::after {
    content: ""; position: absolute; z-index: 0; right: 0; bottom: 0;
    width: 75%; height: 75%; border: 2px solid var(--gold);
}
.welcome-image img { position: relative; z-index: 1; }
.experience-stamp {
    position: absolute; z-index: 2; right: -5px; top: 30px;
    width: 110px; height: 110px; background: var(--wine); color: white;
    border: 4px solid var(--cream); border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.experience-stamp strong { font-family: 'Playfair Display',serif; font-size: 29px; }
.experience-stamp span { font-size: 9px; letter-spacing: 1px; }
.feature-strip { background: var(--wine); color: white; padding: 26px 0; }
.feature-stat { text-align: center; border-right: 1px solid #ffffff2c; }
.feature-stat:last-child { border: 0; }
.feature-stat strong { display: block; color: var(--gold); font-family: 'Playfair Display',serif; font-size: 29px; }
.feature-stat span { font-size: 11px; color: #f0dfe3; letter-spacing: 1px; text-transform: uppercase; }
.facility-card {
    height: 100%; padding: 32px 25px; border: 1px solid #f0e6df;
    background: white; transition: .3s;
}
.facility-card:hover { transform: translateY(-6px); box-shadow: 0 18px 40px #4a14200e; border-color: #dfc28f; }
.facility-icon {
    width: 58px; height: 58px; display: grid; place-items: center;
    background: #f8eee7; color: var(--wine); font-size: 25px; margin-bottom: 23px;
}
.facility-card h3 { font-size: 20px; color: var(--wine); }
.facility-card p { font-size: 13px; line-height: 1.8; color: var(--muted); margin-bottom: 0; }
.gallery-card { position: relative; overflow: hidden; height: 270px; background: #eee; }
.gallery-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
.gallery-card:hover img { transform: scale(1.08); }
.gallery-card::after { content:""; position:absolute; inset:35% 0 0; background:linear-gradient(transparent,#1b0913c9); }
.gallery-caption { position:absolute; z-index:2; bottom:18px; left:20px; color:white; }
.gallery-caption h3 { font-size:20px; margin-bottom:4px; }
.gallery-caption span { color:#e5c88f; font-size:10px; letter-spacing:2px; text-transform:uppercase; }
.gallery-empty { padding:40px; background:var(--cream); color:var(--muted); text-align:center; }
.review-card {
    background: white; padding: 32px; height: 100%;
    border: 1px solid #f1e6de; box-shadow: 0 10px 35px #39132408;
}
.review-stars { color: #c49747; letter-spacing: 3px; }
.review-quote { color: var(--wine); font-size: 30px; }
.review-text { color: #75686e; font-size: 14px; line-height: 1.9; min-height: 106px; }
.review-person { display:flex; align-items:center; gap:13px; margin-top:22px; }
.review-avatar {
    width:44px; height:44px; border-radius:50%; display:grid; place-items:center;
    background:var(--wine); color:var(--gold); font-family:'Playfair Display',serif; font-size:20px;
}
.review-person strong { display:block; font-size:13px; }
.review-person small { color:var(--muted); font-size:11px; }
.booking-section { background: var(--wine-dark); position:relative; overflow:hidden; }
.booking-section::before {
    content:""; position:absolute; width:350px; height:350px; border:1px solid #d5ad6828;
    border-radius:50%; right:-100px; top:-120px;
}
.booking-panel { background:white; padding:35px; position:relative; }
.form-label { font-size:11px; font-weight:700; color:#604b52; }
.form-control,.form-select { border-radius:2px; border-color:#eadfdc; padding:12px; font-size:13px; }
.form-control:focus,.form-select:focus { border-color:var(--gold); box-shadow:0 0 0 .2rem #d5ad6820; }
.contact-item { display:flex; gap:15px; margin-bottom:25px; color:#f4e8e9; }
.contact-item i { color:var(--gold); font-size:23px; }
.contact-item strong { display:block; color:white; font-size:13px; margin-bottom:5px; }
.contact-item span,.contact-item a { font-size:12px; color:#d9c7ce; overflow-wrap:anywhere; }
.footer { background:#200811; color:#d5c5cb; padding:60px 0 0; }
.footer-title { color:white; font-family:'Playfair Display',serif; font-size:20px; margin-bottom:20px; }
.footer p,.footer li,.footer a { font-size:12px; line-height:2; color:#c4b1b9; }
.footer a:hover { color:var(--gold); }
.footer-links { list-style:none; padding:0; margin:0; }
.footer-links li { margin-bottom:6px; }
.social-link {
    width:37px; height:37px; display:inline-grid; place-items:center;
    border:1px solid #62404c; color:var(--gold)!important; margin-right:6px;
}
.social-link:hover { background:var(--gold); color:var(--wine-dark)!important; }
.footer-bottom { margin-top:45px; padding:20px 0; border-top:1px solid #ffffff17; font-size:11px; }
.whatsapp-float {
    position:fixed; z-index:99; right:22px; bottom:22px; width:54px; height:54px;
    display:grid; place-items:center; border-radius:50%; background:#20ba66;
    color:white; font-size:28px; box-shadow:0 8px 25px #0003;
}
.back-top {
    position:fixed; z-index:98; right:29px; bottom:88px; width:40px; height:40px;
    display:none; place-items:center; background:var(--gold); color:var(--wine-dark);
}
.back-top.show { display:grid; }
.notice { font-size:13px; }
@media(max-width:767px) {
    .topline { text-align:center; }
    .topline .top-right { display:none; }
    .hero .carousel-item { height:610px; min-height:0; }
    .hero h1 { font-size:43px; }
    .hero p { font-size:14px; }
    .hero .carousel-control-prev,.hero .carousel-control-next { width:10%; }
    .section { padding:65px 0; }
    .welcome-image img { height:320px; }
    .feature-stat { border:0; padding:12px 0; }
    .feature-stat strong { font-size:25px; }
    .booking-panel { padding:22px; }
    .gallery-card { height:240px; }
}
</style>
</head>
<body>

<!-- TOP CONTACT BAR -->
<div class="topline">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-geo-alt me-1"></i><?= h($hall['address']) ?>
        </div>
        <div class="top-right">
            <a href="mailto:<?= h($hall['email']) ?>">
                <i class="bi bi-envelope me-1"></i><?= h($hall['email']) ?>
            </a>
        </div>
    </div>
</div>

<!-- HEADER -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container py-2">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#home">
            <span class="brand-mark"><i class="bi bi-flower2"></i></span>
            <span>
                <span class="brand-name d-block"><?= h($hall['hall_name']) ?></span>
                <span class="brand-caption">Weddings · Events · Memories</span>
            </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-label="Toggle navigation">
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
                <li class="nav-item ms-lg-3 mt-3 mt-lg-0">
                    <a class="btn btn-gold" href="#booking">BOOK YOUR DATE <i class="bi bi-arrow-up-right ms-1"></i></a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- FIVE IMAGE SLIDING BANNER -->
<section class="hero" id="home">
    <div id="weddingSlider" class="carousel slide carousel-fade"
         data-bs-ride="carousel" data-bs-interval="4500" data-bs-pause="false">

        <div class="carousel-indicators">
            <?php foreach ($banners as $i => $banner): ?>
                <button type="button" data-bs-target="#weddingSlider"
                        data-bs-slide-to="<?= $i ?>"
                        class="<?= $i === 0 ? 'active' : '' ?>"
                        aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>

        <div class="carousel-inner">
            <?php foreach ($banners as $i => $banner): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <img src="<?= h($banner['image']) ?>"
                         alt="<?= h($banner['title']) ?>"
                         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                    <div class="hero-copy">
                        <div class="container">
                            <div class="hero-inner">
                                <div class="eyebrow">
                                    <i class="bi bi-stars me-2"></i><?= h($banner['tag']) ?>
                                </div>
                                <h1><?= h($banner['title']) ?></h1>
                                <p><?= h($banner['subtitle']) ?></p>
                                <div class="d-flex flex-wrap gap-3 mt-4">
                                    <a href="#booking" class="btn btn-gold">
                                        PLAN YOUR EVENT <i class="bi bi-arrow-right ms-2"></i>
                                    </a>
                                    <a href="#gallery" class="btn btn-outline-gold">
                                        <i class="bi bi-images me-2"></i>EXPLORE GALLERY
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button class="carousel-control-prev" type="button"
                data-bs-target="#weddingSlider" data-bs-slide="prev"
                aria-label="Previous slide">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button"
                data-bs-target="#weddingSlider" data-bs-slide="next"
                aria-label="Next slide">
            <span class="carousel-control-next-icon"></span>
        </button>
        <div class="hero-count">01 <span class="text-warning">/</span> 05</div>
    </div>
</section>

<!-- WELCOME / ABOUT -->
<section class="section" id="about">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="welcome-image">
                    <img src="https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=1000&q=85"
                         alt="Elegant wedding venue" loading="lazy">
                    <div class="experience-stamp">
                        <strong>♥</strong>
                        <span>MADE WITH LOVE</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-label">WELCOME TO OUR VENUE</div>
                <h2 class="section-title mt-3"><?= h($hall['tagline']) ?></h2>
                <div class="gold-line"></div>
                <p class="section-desc">
                    Every celebration tells a beautiful story. At
                    <strong><?= h($hall['hall_name']) ?></strong>, we help you
                    create unforgettable memories with a welcoming venue,
                    elegant surroundings and thoughtful event arrangements.
                </p>
                <p class="section-desc">
                    From wedding ceremonies and receptions to engagements,
                    anniversaries and family gatherings, our venue is designed
                    to make your important moments feel truly special.
                </p>
                <div class="row g-3 mt-3">
                    <div class="col-6">
                        <div class="d-flex gap-2 align-items-center">
                            <i class="bi bi-check-circle-fill text-warning fs-5"></i>
                            <span class="small fw-bold">Elegant Ambience</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex gap-2 align-items-center">
                            <i class="bi bi-check-circle-fill text-warning fs-5"></i>
                            <span class="small fw-bold">Special Occasions</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex gap-2 align-items-center">
                            <i class="bi bi-check-circle-fill text-warning fs-5"></i>
                            <span class="small fw-bold">Flexible Arrangements</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex gap-2 align-items-center">
                            <i class="bi bi-check-circle-fill text-warning fs-5"></i>
                            <span class="small fw-bold">Memorable Moments</span>
                        </div>
                    </div>
                </div>
                <a href="#booking" class="btn btn-gold mt-4">
                    ENQUIRE ABOUT YOUR EVENT <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- QUICK HIGHLIGHTS -->
<section class="feature-strip">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-lg-3 feature-stat">
                <strong><i class="bi bi-heart-fill"></i></strong>
                <span>Wedding Celebrations</span>
            </div>
            <div class="col-6 col-lg-3 feature-stat">
                <strong><i class="bi bi-stars"></i></strong>
                <span>Elegant Decoration</span>
            </div>
            <div class="col-6 col-lg-3 feature-stat">
                <strong><i class="bi bi-people-fill"></i></strong>
                <span>Family Gatherings</span>
            </div>
            <div class="col-6 col-lg-3 feature-stat">
                <strong><i class="bi bi-calendar-heart"></i></strong>
                <span>Special Memories</span>
            </div>
        </div>
    </div>
</section>

<!-- FACILITIES -->
<section class="section section-cream" id="facilities">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">EVERY DETAIL MATTERS</div>
            <h2 class="section-title mt-3">Our Facilities & Services</h2>
            <div class="gold-line mx-auto"></div>
            <p class="section-desc mx-auto" style="max-width:620px">
                Thoughtful facilities to help make your wedding, reception or
                special celebration comfortable and memorable.
            </p>
        </div>

        <div class="row g-4">
            <?php if (count($facilities) > 0): ?>
                <?php foreach ($facilities as $facility): ?>
                    <?php
                    $icon = $facility['icon'] ?: ($icons[$facility['facility_name']] ?? 'bi-stars');
                    if (!preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
                        $icon = 'bi-stars';
                    }
                    ?>
                    <div class="col-6 col-lg-3">
                        <div class="facility-card">
                            <div class="facility-icon"><i class="bi <?= h($icon) ?>"></i></div>
                            <h3><?= h($facility['facility_name']) ?></h3>
                            <p><?= h($facility['description']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                $defaultFacilities = [
                    ['bi-building', 'Spacious Marriage Hall', 'A welcoming venue for weddings and family celebrations.'],
                    ['bi-flower1', 'Wedding Decoration', 'Elegant decoration options for your special day.'],
                    ['bi-cup-hot', 'Catering Services', 'Dining arrangements to suit your event.'],
                    ['bi-car-front', 'Parking Facility', 'Convenient parking arrangements for guests.']
                ];
                foreach ($defaultFacilities as $facility):
                ?>
                    <div class="col-6 col-lg-3">
                        <div class="facility-card">
                            <div class="facility-icon"><i class="bi <?= h($facility[0]) ?>"></i></div>
                            <h3><?= h($facility[1]) ?></h3>
                            <p><?= h($facility[2]) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
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
            <?php if (count($gallery) > 0): ?>
                <?php foreach ($gallery as $image): ?>
                    <div class="col-6 col-lg-3">
                        <a href="<?= h($image['image_path']) ?>"
                           class="gallery-card d-block"
                           data-gallery-image="<?= h($image['image_path']) ?>"
                           data-gallery-title="<?= h($image['title']) ?>">
                            <img src="<?= h($image['image_path']) ?>"
                                 alt="<?= h($image['title']) ?>" loading="lazy">
                            <div class="gallery-caption">
                                <span><?= h($image['category']) ?></span>
                                <h3><?= h($image['title']) ?></h3>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                $fallbackGallery = [
                    ['https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=700&q=80', 'Wedding Ceremony', 'WEDDING'],
                    ['https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=700&q=80', 'Elegant Celebration', 'DECORATION'],
                    ['https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=700&q=80', 'Beautiful Details', 'CELEBRATION'],
                    ['https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=700&q=80', 'Special Moments', 'MEMORIES']
                ];
                foreach ($fallbackGallery as $image):
                ?>
                    <div class="col-6 col-lg-3">
                        <a href="<?= h($image[0]) ?>" class="gallery-card d-block"
                           data-gallery-image="<?= h($image[0]) ?>"
                           data-gallery-title="<?= h($image[1]) ?>">
                            <img src="<?= h($image[0]) ?>" alt="<?= h($image[1]) ?>" loading="lazy">
                            <div class="gallery-caption">
                                <span><?= h($image[2]) ?></span>
                                <h3><?= h($image[1]) ?></h3>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- VISITOR REVIEWS -->
<section class="section section-cream" id="reviews">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">KIND WORDS</div>
            <h2 class="section-title mt-3">Visitor Reviews</h2>
            <div class="gold-line mx-auto"></div>
            <p class="section-desc">Every celebration is special, and every memory matters.</p>
        </div>

        <div id="reviewSlider" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5500">
            <div class="carousel-inner">
                <?php foreach (array_chunk($reviews, 3) as $groupIndex => $reviewGroup): ?>
                    <div class="carousel-item <?= $groupIndex === 0 ? 'active' : '' ?>">
                        <div class="row g-4">
                            <?php foreach ($reviewGroup as $review): ?>
                                <div class="col-md-4">
                                    <article class="review-card">
                                        <div class="d-flex justify-content-between">
                                            <span class="review-quote"><i class="bi bi-quote"></i></span>
                                            <span class="review-stars" aria-label="<?= (int)$review['stars'] ?> out of 5 stars">
                                                <?php for ($s = 0; $s < $review['stars']; $s++): ?>
                                                    <i class="bi bi-star-fill"></i>
                                                <?php endfor; ?>
                                            </span>
                                        </div>
                                        <p class="review-text mt-3"><?= h($review['review']) ?></p>
                                        <div class="review-person">
                                            <div class="review-avatar"><?= h(mb_substr($review['name'], 0, 1)) ?></div>
                                            <div>
                                                <strong><?= h($review['name']) ?></strong>
                                                <small><?= h($review['event']) ?></small>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <button class="btn btn-outline-secondary rounded-circle" type="button"
                        data-bs-target="#reviewSlider" data-bs-slide="prev" aria-label="Previous reviews">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <button class="btn btn-outline-secondary rounded-circle" type="button"
                        data-bs-target="#reviewSlider" data-bs-slide="next" aria-label="Next reviews">
                    <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
        <p class="text-center text-secondary mt-4 small">
            Sample reviews for website demonstration. Replace these with genuine visitor feedback.
        </p>
    </div>
</section>

<!-- BOOKING + CONTACT -->
<section class="section booking-section" id="booking">
    <div class="container position-relative">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5 text-white" id="contact">
                <div class="section-label">LET'S MAKE IT SPECIAL</div>
                <h2 class="text-white mt-3" style="font-size:clamp(35px,4vw,48px)">
                    Your Perfect Day Starts Here
                </h2>
                <div class="gold-line"></div>
                <p style="color:#dfcbd1;line-height:1.9;font-size:14px">
                    Tell us about your event. Send a booking enquiry and our team
                    can follow up with you about availability and arrangements.
                </p>

                <div class="mt-5">
                    <div class="contact-item">
                        <i class="bi bi-geo-alt"></i>
                        <div><strong>OUR LOCATION</strong><span><?= h($hall['address']) ?></span></div>
                    </div>
                    <div class="contact-item">
                        <i class="bi bi-telephone"></i>
                        <div>
                            <strong>CALL US</strong>
                            <a href="tel:<?= h($phone) ?>"><?= h($hall['phone']) ?></a>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="bi bi-envelope"></i>
                        <div>
                            <strong>EMAIL US</strong>
                            <a href="mailto:<?= h($hall['email']) ?>"><?= h($hall['email']) ?></a>
                        </div>
                    </div>
                    <?php if ($whatsapp !== ''): ?>
                    <a class="btn btn-outline-gold mt-2"
                       href="https://wa.me/<?= h($whatsapp) ?>?text=Hello%2C%20I%20would%20like%20to%20enquire%20about%20your%20marriage%20hall."
                       target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp me-2"></i>CHAT ON WHATSAPP
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="booking-panel">
                    <div class="section-label">BOOKING ENQUIRY</div>
                    <h3 class="section-title mt-2 mb-2">Plan Your Celebration</h3>
                    <p class="section-desc mb-4">Complete the form below to send us your event details.</p>

                    <?php if ($bookingMessage !== ''): ?>
                        <div class="alert <?= strpos($bookingMessage, 'Thank you!') === 0 ? 'alert-success' : 'alert-warning' ?> notice">
                            <?= h($bookingMessage) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="#booking">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">YOUR FULL NAME *</label>
                                <input class="form-control" type="text" name="customer_name"
                                       maxlength="150" required
                                       value="<?= h($_POST['customer_name'] ?? '') ?>"
                                       placeholder="Enter your name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PHONE NUMBER *</label>
                                <input class="form-control" type="tel" name="phone"
                                       maxlength="20" required
                                       value="<?= h($_POST['phone'] ?? '') ?>"
                                       placeholder="Enter phone number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">EMAIL ADDRESS</label>
                                <input class="form-control" type="email" name="email"
                                       maxlength="150"
                                       value="<?= h($_POST['email'] ?? '') ?>"
                                       placeholder="Enter email address">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">EVENT TYPE *</label>
                                <select class="form-select" name="event_type" required>
                                    <option value="">Select event</option>
                                    <?php foreach (['Wedding','Reception','Engagement','Birthday Party','Anniversary','Family Function','Other'] as $eventType): ?>
                                        <option value="<?= h($eventType) ?>"
                                            <?= (($_POST['event_type'] ?? '') === $eventType) ? 'selected' : '' ?>>
                                            <?= h($eventType) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">EVENT DATE *</label>
                                <input class="form-control" type="date" name="event_date"
                                       min="<?= date('Y-m-d') ?>" required
                                       value="<?= h($_POST['event_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">EXPECTED GUESTS *</label>
                                <input class="form-control" type="number" name="guest_count"
                                       min="1" max="100000" required
                                       value="<?= h($_POST['guest_count'] ?? '200') ?>"
                                       placeholder="Number of guests">
                            </div>
                            <div class="col-12">
                                <label class="form-label">TELL US MORE</label>
                                <textarea class="form-control" name="message" rows="3"
                                          maxlength="3000"
                                          placeholder="Decoration, catering or other requirements..."><?= h($_POST['message'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 mt-4">
                                <button class="btn btn-gold w-100" type="submit" name="submit_booking">
                                    SEND BOOKING ENQUIRY <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    <p class="small text-secondary mt-3 mb-0">
                        <i class="bi bi-shield-check me-1"></i>
                        Your enquiry will be saved as pending until the hall confirms it.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4 col-md-6">
                <a class="d-flex align-items-center gap-2 mb-4" href="#home">
                    <span class="brand-mark" style="background:#fffaf3">
                        <i class="bi bi-flower2"></i>
                    </span>
                    <span>
                        <span class="brand-name d-block" style="color:white"><?= h($hall['hall_name']) ?></span>
                        <span class="brand-caption" style="color:#d5ad68">WEDDINGS · EVENTS · MEMORIES</span>
                    </span>
                </a>
                <p>
                    A special place for life's most meaningful celebrations.
                    Let us help you make beautiful memories with your family
                    and friends.
                </p>
                <div class="mt-4">
                    <a class="social-link" href="https://facebook.com/" aria-label="Facebook" target="_blank" rel="noopener"><i class="bi bi-facebook"></i></a>
                    <a class="social-link" href="https://instagram.com/" aria-label="Instagram" target="_blank" rel="noopener"><i class="bi bi-instagram"></i></a>
                    <a class="social-link" href="https://youtube.com/" aria-label="YouTube" target="_blank" rel="noopener"><i class="bi bi-youtube"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <h3 class="footer-title">Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#facilities">Facilities</a></li>
                    <li><a href="#gallery">Gallery</a></li>
                    <li><a href="#reviews">Reviews</a></li>
                    <li><a href="#booking">Booking Enquiry</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h3 class="footer-title">Our Services</h3>
                <ul class="footer-links">
                    <li><a href="#facilities">Wedding Ceremony</a></li>
                    <li><a href="#facilities">Reception Party</a></li>
                    <li><a href="#facilities">Engagement Ceremony</a></li>
                    <li><a href="#facilities">Birthday Celebration</a></li>
                    <li><a href="#facilities">Family Functions</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h3 class="footer-title">Get in Touch</h3>
                <p><i class="bi bi-geo-alt me-2 text-warning"></i><?= h($hall['address']) ?></p>
                <p><i class="bi bi-telephone me-2 text-warning"></i><a href="tel:<?= h($phone) ?>"><?= h($hall['phone']) ?></a></p>
                <p><i class="bi bi-envelope me-2 text-warning"></i><a href="mailto:<?= h($hall['email']) ?>"><?= h($hall['email']) ?></a></p>
                <a href="#booking" class="btn btn-gold mt-2">ENQUIRE NOW</a>
            </div>
        </div>

        <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> <?= h($hall['hall_name']) ?>. All rights reserved.</span>
            <span>Designed for beautiful beginnings <i class="bi bi-heart-fill text-warning"></i></span>
        </div>
    </div>
</footer>

<!-- IMAGE LIGHTBOX -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="galleryModalTitle"></h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <img id="galleryModalImage" src="" alt=""
                     class="img-fluid" style="max-height:78vh;object-fit:contain">
            </div>
        </div>
    </div>
</div>

<a class="whatsapp-float"
   href="https://wa.me/<?= h($whatsapp) ?>?text=Hello%2C%20I%20would%20like%20to%20enquire%20about%20your%20marriage%20hall."
   target="_blank" rel="noopener" aria-label="Contact us on WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>
<a href="#home" class="back-top" id="backTop" aria-label="Back to top">
    <i class="bi bi-arrow-up"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Back-to-top button
    const backTop = document.getElementById('backTop');
    window.addEventListener('scroll', function () {
        backTop.classList.toggle('show', window.scrollY > 350);
    });

    // Close mobile navigation after clicking a section link
    document.querySelectorAll('#mainNav .nav-link, #mainNav .btn').forEach(function (link) {
        link.addEventListener('click', function () {
            const nav = document.getElementById('mainNav');
            if (nav.classList.contains('show')) {
                bootstrap.Collapse.getOrCreateInstance(nav).hide();
            }
        });
    });

    // Gallery lightbox
    const galleryModalElement = document.getElementById('galleryModal');
    const galleryModal = new bootstrap.Modal(galleryModalElement);
    const modalImage = document.getElementById('galleryModalImage');
    const modalTitle = document.getElementById('galleryModalTitle');

    document.querySelectorAll('[data-gallery-image]').forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();
            modalImage.src = item.dataset.galleryImage;
            modalImage.alt = item.dataset.galleryTitle || 'Wedding gallery image';
            modalTitle.textContent = item.dataset.galleryTitle || 'Gallery';
            galleryModal.show();
        });
    });

    galleryModalElement.addEventListener('hidden.bs.modal', function () {
        modalImage.src = '';
    });

    // Update the slide counter for the five-image banner
    const hero = document.getElementById('weddingSlider');
    hero.addEventListener('slid.bs.carousel', function (event) {
        const current = String(event.to + 1).padStart(2, '0');
        hero.querySelector('.hero-count').innerHTML =
            current + ' <span class="text-warning">/</span> 05';
    });
});
</script>
</body>
</html>
