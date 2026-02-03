<?php

/**
 * Contact Form Handler - Elsesser & Co.
 * Handles form submission, validation, and email notification
 */

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Метод не разрешён'
    ]);
    exit;
}

/**
 * Sanitize input string
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number
 */
function isValidPhone($phone) {
    // Remove all non-digit characters except +
    $cleaned = preg_replace('/[^\d\+]/', '', $phone);
    // Check if it's at least 8 digits
    return strlen(preg_replace('/\D/', '', $cleaned)) >= 8;
}

// Configuration
$config = [
    'admin_email' => 'info@elsesser.ae',
    'site_name' => 'Elsesser & Co.',
    'subject' => 'Новая заявка на оценку недвижимости'
];

// Get and sanitize form data
$data = [
    'first_name' => sanitize($_POST['first_name'] ?? ''),
    'last_name' => sanitize($_POST['last_name'] ?? ''),
    'email' => sanitize($_POST['email'] ?? ''),
    'phone' => sanitize($_POST['phone'] ?? ''),
    'offering_type' => sanitize($_POST['offering_type'] ?? ''),
    'property_address' => sanitize($_POST['property_address'] ?? ''),
    'preferred_date' => sanitize($_POST['preferred_date'] ?? ''),
    'preferred_time' => sanitize($_POST['preferred_time'] ?? ''),
    'message' => sanitize($_POST['message'] ?? '')
];

// Validation errors array
$errors = [];

// Validate required fields
if (empty($data['first_name']) || strlen($data['first_name']) < 2) {
    $errors[] = 'Введите корректное имя';
}

if (empty($data['last_name']) || strlen($data['last_name']) < 2) {
    $errors[] = 'Введите корректную фамилию';
}

if (empty($data['email']) || !isValidEmail($data['email'])) {
    $errors[] = 'Введите корректный email';
}

if (empty($data['phone']) || !isValidPhone($data['phone'])) {
    $errors[] = 'Введите корректный номер телефона';
}

if (empty($data['offering_type'])) {
    $errors[] = 'Выберите тип предложения';
}

if (empty($data['property_address']) || strlen($data['property_address']) < 5) {
    $errors[] = 'Введите адрес недвижимости';
}

// Return validation errors if any
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $errors[0],
        'errors' => $errors
    ]);
    exit;
}

// Translate offering type
$offeringTypes = [
    'sell' => 'Продажа',
    'rent' => 'Сдача в аренду',
    'valuation' => 'Только оценка'
];
$offeringTypeRu = $offeringTypes[$data['offering_type']] ?? $data['offering_type'];

// Build email content
$emailBody = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: #00736c; border-bottom: 2px solid #00736c; padding-bottom: 10px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #1a2447; }
        .value { margin-left: 10px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Новая заявка на оценку недвижимости</h1>
        
        <div class='field'>
            <span class='label'>Имя:</span>
            <span class='value'>{$data['first_name']} {$data['last_name']}</span>
        </div>
        
        <div class='field'>
            <span class='label'>Email:</span>
            <span class='value'>{$data['email']}</span>
        </div>
        
        <div class='field'>
            <span class='label'>Телефон:</span>
            <span class='value'>{$data['phone']}</span>
        </div>
        
        <div class='field'>
            <span class='label'>Тип предложения:</span>
            <span class='value'>{$offeringTypeRu}</span>
        </div>
        
        <div class='field'>
            <span class='label'>Адрес недвижимости:</span>
            <span class='value'>{$data['property_address']}</span>
        </div>
";

if (!empty($data['preferred_date'])) {
    $formattedDate = date('d.m.Y', strtotime($data['preferred_date']));
    $emailBody .= "
        <div class='field'>
            <span class='label'>Предпочитаемая дата:</span>
            <span class='value'>{$formattedDate}</span>
        </div>
    ";
}

if (!empty($data['preferred_time'])) {
    $emailBody .= "
        <div class='field'>
            <span class='label'>Предпочитаемое время:</span>
            <span class='value'>{$data['preferred_time']}</span>
        </div>
    ";
}

if (!empty($data['message'])) {
    $emailBody .= "
        <div class='field'>
            <span class='label'>Дополнительная информация:</span>
            <p>{$data['message']}</p>
        </div>
    ";
}

$emailBody .= "
        <div class='footer'>
            <p>Отправлено с сайта {$config['site_name']}</p>
            <p>Дата: " . date('d.m.Y H:i') . "</p>
            <p>IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Неизвестно') . "</p>
        </div>
    </div>
</body>
</html>
";

// Email headers
$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: ' . $config['site_name'] . ' <noreply@elsesser.ae>',
    'Reply-To: ' . $data['email'],
    'X-Mailer: PHP/' . phpversion()
];

// Send email
$emailSent = mail(
    $config['admin_email'],
    $config['subject'] . ' - ' . $data['first_name'] . ' ' . $data['last_name'],
    $emailBody,
    implode("\r\n", $headers)
);

// Log the submission
$logEntry = date('Y-m-d H:i:s') . ' | ' . 
    $data['first_name'] . ' ' . $data['last_name'] . ' | ' . 
    $data['email'] . ' | ' . 
    $data['phone'] . ' | ' . 
    $data['property_address'] . ' | ' .
    ($emailSent ? 'EMAIL_SENT' : 'EMAIL_FAILED') . "\n";

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Write to log file
file_put_contents($logsDir . '/contacts.log', $logEntry, FILE_APPEND | LOCK_EX);

// Send response
if ($emailSent) {
    echo json_encode([
        'success' => true,
        'message' => 'Спасибо! Ваша заявка успешно отправлена. Мы свяжемся с вами в ближайшее время.'

    ]);
} else {
    // Even if email fails, we've logged the submission
    // Return success to user but log the issue
    echo json_encode([
        'success' => true,
        'message' => 'Спасибо! Ваша заявка принята. Мы свяжемся с вами в ближайшее время.'
    ]);
}
