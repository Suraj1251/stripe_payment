<?php
require 'config.php';

header('Content-Type: application/json');

// Read user input from fetch (form)
$input = json_decode(file_get_contents("php://input"), true);

$student_id = $input['student_id'];
$student_name = $input['student_name'];
$email = $input['email'];
$amount = intval($input['amount']) * 100; // Convert to paisa

$YOUR_DOMAIN = 'http://localhost/stripe_payment';

try {
    // 1. Create Checkout Session
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'inr',
                'unit_amount' => $amount,
                'product_data' => [
                    'name' => 'School Fees',
                ],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'customer_email' => $email,
        'metadata' => [
            'student_id' => $student_id,
            'student_name' => $student_name
        ],
        'success_url' => $YOUR_DOMAIN . '/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $YOUR_DOMAIN . '/cancel.html',
    ]);

    // 2. Save payment entry as pending
    $conn = new mysqli("localhost", "root", "", "fees_payment");
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection failed']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO payments (student_id, student_name, email, amount, payment_status, payment_id) VALUES (?, ?, ?, ?, 'pending', ?)");
    $stmt->bind_param("sssis", $student_id, $student_name, $email, $amount, $checkout_session->id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // 3. Return Session ID to frontend
    echo json_encode(['id' => $checkout_session->id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
