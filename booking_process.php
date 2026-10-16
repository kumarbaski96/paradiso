<?php
session_start();

include 'config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
//use PHPMailer\PHPMailer\Exception;

//require_once __DIR__ . "/vendor/autoload.php";

function redirectWithError(string $message): void
{
    $_SESSION['booking_error'] = $message;
    header("Location: booking.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['submit_booking'])) {
    header("Location: booking.php");
    exit;
}

// Read and clean form fields.
$name       = trim($_POST['name'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$email      = trim($_POST['email'] ?? '');
$event      = trim($_POST['event'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$end_date   = trim($_POST['end_date'] ?? '');
$guests     = filter_var(
    $_POST['guests'] ?? null,
    FILTER_VALIDATE_INT
);
$message    = trim($_POST['message'] ?? '');

$allowedEvents = [
    'Wedding',
    'Engagement',
    'Reception',
    'Birthday Party',
    'Anniversary',
    'Corporate Event',
    'Other'
];

// Basic validation.
if (
    $name === '' ||
    $phone === '' ||
    $email === '' ||
    $event === '' ||
    $start_date === '' ||
    $end_date === '' ||
    $guests === false ||
    $guests === null ||
    $guests < 1 ||
    $guests > 100000
) {
    redirectWithError("Please fill in all required fields correctly.");
}

if (mb_strlen($name) > 100 ||
    mb_strlen($phone) > 20 ||
    mb_strlen($email) > 150 ||
    mb_strlen($message) > 3000) {
    redirectWithError("One or more fields exceed the allowed length.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError("Please enter a valid email address.");
}

if (!preg_match('/^[0-9+\s().-]{7,20}$/', $phone)) {
    redirectWithError("Please enter a valid phone number.");
}

if (!in_array($event, $allowedEvents, true)) {
    redirectWithError("Please select a valid event type.");
}

// Validate dates strictly.
$start = DateTimeImmutable::createFromFormat('!Y-m-d', $start_date);
$end   = DateTimeImmutable::createFromFormat('!Y-m-d', $end_date);
$today = new DateTimeImmutable('today');

if (
    !$start ||
    !$end ||
    $start->format('Y-m-d') !== $start_date ||
    $end->format('Y-m-d') !== $end_date
) {
    redirectWithError("Please enter valid booking dates.");
}

if ($start < $today) {
    redirectWithError("The start date cannot be in the past.");
}

if ($end < $start) {
    redirectWithError("The end date cannot be before the start date.");
}

// Count both the start date and end date.
$total_days = (int)$start->diff($end)->days + 1;

// Prevent accidental extremely long date ranges.
if ($total_days > 365) {
    redirectWithError("The booking period cannot exceed 365 days.");
}

$bookingId = 0;

$emailStatus = 'pending';
$whatsappStatus = 'pending';

try {
    // Save booking first. Notification failures won't erase it.
    $sql = "INSERT INTO booking
        (name, phone, email, event, start_date, end_date,
         total_days, guests, message, email_status, whatsapp_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssssiisss",
        $name,
        $phone,
        $email,
        $event,
        $start_date,
        $end_date,
        $total_days,
        $guests,
        $message,
        $emailStatus,
        $whatsappStatus
    );

    $stmt->execute();
    $bookingId = (int)$conn->insert_id;
    $stmt->close();

} catch (Throwable $e) {
    error_log("Booking database error: " . $e->getMessage());
    redirectWithError("We could not save your booking. Please try again.");
}


// Create a readable notification message.
$bookingMessage =
    "New Marriage Hall Booking Enquiry\n\n" .
    "Booking ID: " . $bookingId . "\n" .
    "Name: " . $name . "\n" .
    "Phone: " . $phone . "\n" .
    "Email: " . $email . "\n" .
    "Event: " . $event . "\n" .
    "Start Date: " . $start_date . "\n" .
    "End Date: " . $end_date . "\n" .
    "Total Days: " . $total_days . "\n" .
    "Expected Guests: " . $guests . "\n" .
    "Requirements: " . ($message !== '' ? $message : 'None');


// ----------------------------------------------------
// EMAIL NOTIFICATION CONFIGURATION
// ----------------------------------------------------

$emailSent = false;

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    // Replace with the Gmail account used to send emails.
    $mail->Username   = 'yourgmail@gmail.com';

    // Use a Google App Password, NOT your normal password.
    $mail->Password   = 'your-16-character-app-password';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(
        'yourgmail@gmail.com',
        'Marriage Hall Booking'
    );

    // Replace with the email address that should receive enquiries.
    $mail->addAddress('your-booking-email@example.com');

    // Send a confirmation to the customer as well.
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = "New Booking Enquiry #" . $bookingId;

    $safeMessage = nl2br(
        htmlspecialchars($message ?: 'None', ENT_QUOTES, 'UTF-8')
    );

    $mail->Body = "
        <h2>New Marriage Hall Booking Enquiry</h2>
        <p><strong>Booking ID:</strong> {$bookingId}</p>
        <p><strong>Name:</strong> " .
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Phone:</strong> " .
            htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Email:</strong> " .
            htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Event:</strong> " .
            htmlspecialchars($event, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Start Date:</strong> {$start_date}</p>
        <p><strong>End Date:</strong> {$end_date}</p>
        <p><strong>Total Days:</strong> {$total_days}</p>
        <p><strong>Guests:</strong> {$guests}</p>
        <p><strong>Requirements:</strong><br>{$safeMessage}</p>
    ";

    $mail->AltBody = $bookingMessage;

    $mail->send();
    $emailSent = true;
    $emailStatus = 'sent';

} catch (Throwable $e) {
    error_log(
        "Booking email failed for booking #" . $bookingId .
        ": " . $e->getMessage()
    );

    $emailStatus = 'failed';
}


// ----------------------------------------------------
// WHATSAPP BUSINESS CLOUD API CONFIGURATION
// ----------------------------------------------------

$whatsappSent = false;

// Replace these with actual values from Meta's developer dashboard.
$accessToken = 'YOUR_META_ACCESS_TOKEN';
$phoneNumberId = 'YOUR_WHATSAPP_PHONE_NUMBER_ID';

// Hall's destination number in international format, digits only.
$recipientNumber = '919570103979';

// Replace vXX.X with an API version currently supported by Meta.
$apiVersion = 'vXX.X';

if (
    $accessToken !== 'YOUR_META_ACCESS_TOKEN' &&
    $phoneNumberId !== 'YOUR_WHATSAPP_PHONE_NUMBER_ID' &&
    $apiVersion !== 'vXX.X' &&
    function_exists('curl_init')
) {
    $apiUrl = "https://graph.facebook.com/" .
              $apiVersion . "/" .
              $phoneNumberId . "/messages";

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $recipientNumber,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $bookingMessage
        ]
    ];

    $ch = curl_init($apiUrl);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $apiResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $decodedResponse = is_string($apiResponse)
        ? json_decode($apiResponse, true)
        : null;

    if (
        $curlError === '' &&
        $httpCode >= 200 &&
        $httpCode < 300 &&
        isset($decodedResponse['messages'][0]['id'])
    ) {
        $whatsappSent = true;
        $whatsappStatus = 'sent';
    } else {
        $whatsappStatus = 'failed';

        error_log(
            "WhatsApp notification failed for booking #" .
            $bookingId .
            ". HTTP: " . $httpCode .
            ". Response: " . (string)$apiResponse .
            ". cURL error: " . $curlError
        );
    }
} else {
    $whatsappStatus = 'pending';

    error_log(
        "WhatsApp API not configured or cURL unavailable for booking #" .
        $bookingId
    );
}


// ----------------------------------------------------
// UPDATE NOTIFICATION STATUSES
// ----------------------------------------------------

try {
    $update = $conn->prepare(
        "UPDATE booking
         SET email_status = ?, whatsapp_status = ?
         WHERE id = ?"
    );

    $update->bind_param(
        "ssi",
        $emailStatus,
        $whatsappStatus,
        $bookingId
    );

    $update->execute();
    $update->close();

} catch (Throwable $e) {
    error_log(
        "Could not update notification statuses for booking #" .
        $bookingId . ": " . $e->getMessage()
    );
}


// ----------------------------------------------------
// DISPLAY RESULT
// ----------------------------------------------------

if ($emailSent && $whatsappSent) {
    $_SESSION['booking_success'] =
        "Booking enquiry #" . $bookingId .
        " was saved and both notifications were accepted by their services.";

} else {
    $_SESSION['booking_success'] =
        "Booking enquiry #" . $bookingId .
        " was saved successfully. Email status: " .
        $emailStatus . ". WhatsApp status: " . $whatsappStatus .
        ". The hall can check the saved enquiry if a notification failed.";
}

header("Location: index.php");
exit;