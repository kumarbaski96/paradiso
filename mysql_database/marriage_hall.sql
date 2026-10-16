-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 16, 2026 at 11:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `marriage_hall`
--

-- --------------------------------------------------------

--
-- Table structure for table `about`
--

CREATE TABLE `about` (
  `id` int(11) NOT NULL,
  `section_label` varchar(150) NOT NULL,
  `heading` varchar(255) NOT NULL,
  `image` varchar(500) NOT NULL,
  `image_alt` varchar(255) DEFAULT 'Beautiful marriage hall setting',
  `description1` text NOT NULL,
  `description2` text NOT NULL,
  `feature1` varchar(150) NOT NULL,
  `feature2` varchar(150) NOT NULL,
  `feature3` varchar(150) NOT NULL,
  `feature4` varchar(150) NOT NULL,
  `stamp_text` varchar(100) DEFAULT 'MADE WITH LOVE',
  `button_text` varchar(150) DEFAULT 'ENQUIRE ABOUT YOUR EVENT',
  `button_link` varchar(255) DEFAULT '#booking',
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `about`
--

INSERT INTO `about` (`id`, `section_label`, `heading`, `image`, `image_alt`, `description1`, `description2`, `feature1`, `feature2`, `feature3`, `feature4`, `stamp_text`, `button_text`, `button_link`, `status`, `created_at`) VALUES
(1, 'WELCOME TO OUR VENUE1234', 'Where Your Forever Begins123', '../uploads/about/21adb26b06712e0bf267e40fb40c24fb.jpg', 'Beautiful marriage hall setting1', 'Every celebration tells a beautiful story. At Royal Marriage Hall, we help you create unforgettable memories with a welcoming venue, elegant surroundings and thoughtful event arrangements.', 'From wedding ceremonies and receptions to engagements, anniversaries and family gatherings, our venue is designed to make your important moments feel truly special.', 'Elegant Ambience', 'Special Occasions', 'Flexible Arrangements', 'Memorable Moments', 'MADE WITH LOVE1', 'ENQUIRE ABOUT YOUR EVENT', '#booking', 1, '2026-10-15 07:36:25');

-- --------------------------------------------------------

--
-- Table structure for table `about_content`
--

CREATE TABLE `about_content` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `Marriage_hall_name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `district` varchar(100) NOT NULL,
  `state` varchar(50) NOT NULL,
  `pin_code` int(7) NOT NULL,
  `mobile1` varchar(11) DEFAULT NULL,
  `mobile2` varchar(11) DEFAULT NULL,
  `mobile3` varchar(11) DEFAULT NULL,
  `email1` varchar(100) DEFAULT NULL,
  `alt_email` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `Marriage_hall_name`, `username`, `email`, `password`, `address`, `district`, `state`, `pin_code`, `mobile1`, `mobile2`, `mobile3`, `email1`, `alt_email`, `status`, `created_at`) VALUES
(1, 'Nitesh Singh', 'Paradiso The Venue', 'Admin', 'paradisovenue2@gmail.com', '$2y$10$h8biuvsym1JjGGY.bJynFeMO6zXT21NZm7OOX9Z.oZGGpP4Mnq7t2', 'Govindpur Tundi Road Opposite khudiya River Road, Dhanbad, Jharkhand, India', 'Dhanbad', 'Jharkhand', 828109, '9065730624', '9128538877', '1111111111', 'paradisovenue2@gmail.com', 'demo@gmail.com', 1, '2026-10-15 05:53:36');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `banner_title` varchar(200) NOT NULL,
  `banner_subtitle` varchar(255) NOT NULL,
  `banner_description` text DEFAULT NULL,
  `banner_image` varchar(255) NOT NULL,
  `button_text` varchar(100) DEFAULT 'GET IN TOUCH',
  `button_link` varchar(255) DEFAULT '#contact',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `banner_title`, `banner_subtitle`, `banner_description`, `banner_image`, `button_text`, `button_link`, `sort_order`, `status`, `created_at`) VALUES
(1, 'The Celebration<br>Begins Here', 'MAKE IT UNFORGETTABLE', 'Your occasion deserves a venue as special as you are.', 'img/b5.jpeg', 'GET IN TOUCH', '#contact', 1, 1, '2026-10-15 04:09:36'),
(2, 'A Dream Wedding<br>Come True 2', 'YOUR PERFECT WEDDING2', 'Celebrate your love in a beautiful and unforgettable setting.', 'img/b2.jpeg', 'EXPLORE VENUE', '#about', 2, 1, '2026-10-15 04:09:36'),
(3, 'Celebrate Every<br>Special Moment 3', 'MEMORIES THAT LAST FOREVER 3', 'Make every family gathering and special occasion extraordinary.', 'img/b3.jpeg', 'BOOK YOUR EVENT', '#contact', 3, 1, '2026-10-15 04:09:36'),
(4, 'Where Elegance<br>Meets Celebration 4', 'LUXURY AND COMFORT 4', 'An elegant venue designed for your most cherished celebrations.', 'img/b4.jpeg', 'VIEW OUR VENUE', '#gallery', 4, 1, '2026-10-15 04:09:36'),
(5, 'Your Big Day,<br>Our Grand Venue 5', 'LET THE MAGIC BEGIN 5', 'Create beautiful memories with your loved ones at our venue.', 'img/b1.jpeg', 'PLAN YOUR EVENT', '#contact', 5, 1, '2026-10-15 04:09:36');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `event` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `guests` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `email_status` varchar(20) DEFAULT 'pending',
  `whatsapp_status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rest_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Pending','Partially Paid','Paid','Refunded') NOT NULL DEFAULT 'Pending',
  `booking_status` enum('Enquiry','Confirmed','Cancelled','Completed') NOT NULL DEFAULT 'Enquiry'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`id`, `name`, `phone`, `email`, `event`, `start_date`, `end_date`, `total_days`, `guests`, `message`, `email_status`, `whatsapp_status`, `created_at`, `total_amount`, `paid_amount`, `rest_amount`, `payment_status`, `booking_status`) VALUES
(1, 'Baski Kumar1', '7488162756', 'baski12.kumar@gmail.com', 'Reception', '2026-10-17', '2026-10-19', 3, 300, 'hello', 'failed', 'pending', '2026-10-16 09:24:31', 0.00, 0.00, 0.00, 'Pending', 'Enquiry'),
(2, 'sanoj kumar', '7250123254', 'sanoj12@gmail.com', 'Wedding', '2026-10-17', '2026-10-22', 6, 400, 'thanks', 'failed', 'pending', '2026-10-16 10:34:06', 1000.00, 1000.00, 0.00, 'Paid', 'Confirmed'),
(3, 'Nitesh', '7061163249', 'niteshsingh9555@gmail.com', 'Anniversary', '2026-10-17', '2026-10-18', 2, 400, 'we need your help', 'failed', 'pending', '2026-10-16 14:18:25', 10000.00, 1200.00, 8800.00, 'Partially Paid', 'Enquiry');

-- --------------------------------------------------------

--
-- Table structure for table `contact_enquiries`
--

CREATE TABLE `contact_enquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('New','Contacted','Closed') DEFAULT 'New',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `facility_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `facility_name`, `description`, `icon`, `status`, `display_order`) VALUES
(1, 'Spacious Hall', 'A beautiful venue for weddings and celebrations.', 'bi-building', 1, 1),
(2, 'Catering Service', 'Food arrangements for your special occasions.', 'bi-cup-hot', 1, 2),
(3, 'Decoration', 'Elegant wedding and event decoration.', 'bi-flower1', 1, 3),
(4, 'Parking', 'Convenient parking for guests.', 'bi-car-front', 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`id`, `title`, `category`, `image`, `status`, `sort_order`, `created_at`) VALUES
(1, 'Wedding Ceremony1234', 'WEDDING11234', '../uploads/gallery/3477747dab6eb3625f53ea01706b616a.jpg', 1, 1, '2026-10-15 09:39:02'),
(2, 'Elegant Decoration', 'DECORATION', '../uploads/gallery/f92d11302390bc678eab436038bf3e3a.jpg', 1, 2, '2026-10-15 09:39:02'),
(3, 'Beautiful Details', 'CELEBRATION', '../uploads/gallery/7b8f588d02d9b60984b04006c79eb1a6.jpg', 1, 3, '2026-10-15 09:39:02'),
(4, 'Special Moments', 'MEMORIES', '../uploads/gallery/5da40b93442b654fc772cca0b4f77508.jpg', 1, 4, '2026-10-15 09:39:02'),
(5, 'Reception Setting', 'RECEPTION', '../uploads/gallery/8fc77ddcf76c70884cafe3bb0b6079d7.jpg', 1, 5, '2026-10-15 09:39:02'),
(6, 'Wedding Flowers', 'FLOWERS', 'https://images.unsplash.com/photo-1523438885200-e635ba2c371e?auto=format&fit=crop&w=700&q=80', 1, 6, '2026-10-15 09:39:02'),
(7, 'Wedding Table', 'DINING1', 'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=700&q=80', 1, 7, '2026-10-15 09:39:02'),
(8, 'Grand Celebration', 'EVENT', 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=700&q=80', 1, 8, '2026-10-15 09:39:02');

-- --------------------------------------------------------

--
-- Table structure for table `gallery_images`
--

CREATE TABLE `gallery_images` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT 'Wedding',
  `image_path` varchar(255) NOT NULL,
  `caption` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logo`
--

CREATE TABLE `logo` (
  `id` int(11) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `text_logo` varchar(150) DEFAULT 'Paradiso The Venue Logo',
  `link` varchar(255) DEFAULT 'index.php',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logo`
--

INSERT INTO `logo` (`id`, `logo`, `text_logo`, `link`, `status`, `created_at`, `updated_at`) VALUES
(1, 'uploads/logos/3aa49e2a08b16ef8fd5012c3eb6e4ec0.jpg', 'Paradiso The Venue Logo', 'index.php', 0, '2026-10-16 11:25:01', '2026-10-16 20:48:49');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `event_type` varchar(150) NOT NULL,
  `review_text` text NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `customer_name`, `event_type`, `review_text`, `rating`, `status`, `sort_order`, `created_at`) VALUES
(1, 'Amit Kumar1', 'Wedding Celebration', 'A lovely venue for our family celebration. The ambience and arrangements made our special day memorable.', 4, 1, 1, '2026-10-15 10:49:16'),
(2, 'Priya Sharma2', 'Wedding Reception', 'Beautiful surroundings and a welcoming atmosphere. It was a wonderful place to celebrate with family.', 5, 1, 2, '2026-10-15 10:49:16'),
(3, 'Rahul Singh3', 'Engagement Ceremony', 'We enjoyed celebrating our engagement here. The venue gave our event a very special feeling.', 2, 1, 3, '2026-10-15 10:49:16'),
(4, 'Neha Kumari4', 'Family Function', 'A comfortable place for a family function. The decorations made the celebration feel warm and special.', 5, 1, 4, '2026-10-15 10:49:16'),
(5, 'Suman Verma5', 'Anniversary', 'A lovely setting for celebrating important moments with friends and family.', 1, 1, 5, '2026-10-15 10:49:16'),
(6, 'Manish Gupta5', 'Birthday Celebration', 'A beautiful venue to consider for a special occasion. The overall atmosphere is elegant.', 5, 1, 6, '2026-10-15 10:49:16'),
(7, 'Baski Kumar Saw', 'My Weddind', 'Felling awsome with this Place', 3, 1, 7, '2026-10-15 11:02:50'),
(8, 'Nitesh Singh', 'wedding celebration', 'Mesmrising moment and great hospitality!', 5, 1, 8, '2026-10-16 14:12:08');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_title` varchar(150) NOT NULL,
  `service_description` text NOT NULL,
  `service_icon` varchar(100) NOT NULL DEFAULT 'bi-building',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_title`, `service_description`, `service_icon`, `sort_order`, `status`, `created_at`) VALUES
(1, 'Spacious Hall11', 'A welcoming venue for weddings and large family gatherings.', 'bi-building', 1, 1, '2026-10-15 08:05:48'),
(2, 'Wedding Decoration', 'Elegant decoration options for stages, entrances and receptions.', 'bi-flower1', 2, 1, '2026-10-15 08:05:48'),
(3, 'Catering Services', 'Dining arrangements and catering options for your guests.', 'bi-cup-hot', 3, 1, '2026-10-15 08:05:48'),
(4, 'Parking Facility', 'Convenient parking arrangements for attending guests.', 'bi-car-front', 4, 1, '2026-10-15 08:05:48'),
(5, 'Lighting & Sound', 'Lighting and sound options for celebrations and receptions.', 'bi-speaker', 5, 1, '2026-10-15 08:05:48'),
(6, 'Event Assistance', 'Helpful coordination for planning your special occasion.', 'bi-calendar-heart', 6, 1, '2026-10-15 08:05:48'),
(7, 'Guest Seating', 'Seating arrangements to suit your event requirements.', 'bi-people', 7, 1, '2026-10-15 08:05:48'),
(8, 'Photo Spaces', 'Beautiful spots for memorable wedding photographs.', 'bi-camera', 8, 1, '2026-10-15 08:05:48');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `hall_name` varchar(150) NOT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `youtube_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `hall_name`, `tagline`, `logo`, `phone`, `email`, `address`, `whatsapp_number`, `facebook_url`, `instagram_url`, `youtube_url`, `updated_at`) VALUES
(1, 'Paradiso The Venue', 'A Perfect Venue for Your Special Day', NULL, '9876543210', 'info@example.com', 'Jharia, Dhanbad, Jharkhand, India', '919876543210', NULL, NULL, NULL, '2026-10-15 03:29:37');

-- --------------------------------------------------------

--
-- Table structure for table `social_networking`
--

CREATE TABLE `social_networking` (
  `id` int(11) NOT NULL,
  `platform_name` varchar(50) NOT NULL,
  `social_url` varchar(500) NOT NULL,
  `icon_class` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `social_networking`
--

INSERT INTO `social_networking` (`id`, `platform_name`, `social_url`, `icon_class`, `status`, `created_at`, `sort_order`) VALUES
(1, 'Facebook', 'https://www.facebook.com/share/1CDGeAD3jg/', 'bi bi-facebook', 1, '2026-10-16 12:17:20', 1),
(2, 'Instagram', 'https://instagram.com/', 'bi bi-instagram', 1, '2026-10-16 12:17:20', 2),
(3, 'YouTube', 'https://youtube.com/', 'bi bi-youtube', 0, '2026-10-16 12:17:20', 3),
(4, 'Google', 'https://www.google.com/', 'bi bi-google', 1, '2026-10-16 12:18:02', 5),
(5, 'WhatsApp', 'https://wa.me/919876543210', 'bi bi-whatsapp', 1, '2026-10-16 12:18:02', 4),
(13, 'Google Maps', 'https://maps.google.com/', 'bi bi-geo-alt', 0, '2026-10-16 12:18:02', 6);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about`
--
ALTER TABLE `about`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `about_content`
--
ALTER TABLE `about_content`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_enquiries`
--
ALTER TABLE `contact_enquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gallery_images`
--
ALTER TABLE `gallery_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logo`
--
ALTER TABLE `logo`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `social_networking`
--
ALTER TABLE `social_networking`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about`
--
ALTER TABLE `about`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `about_content`
--
ALTER TABLE `about_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_enquiries`
--
ALTER TABLE `contact_enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `gallery_images`
--
ALTER TABLE `gallery_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logo`
--
ALTER TABLE `logo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `social_networking`
--
ALTER TABLE `social_networking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
