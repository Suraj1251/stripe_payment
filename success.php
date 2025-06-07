<?php
require 'config.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use PHPMailer\PHPMailer\PHPMailer;

$session_id = $_GET['session_id'] ?? '';
if (!$session_id) die("Invalid session");

$session = \Stripe\Checkout\Session::retrieve($session_id);
$payment_id = $session->id;

$conn = new mysqli("localhost", "root", "", "fees_payment");
if ($conn->connect_error) die("DB error");

$stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id=? LIMIT 1");
$stmt->bind_param("s", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) die("Payment not found");

// ✅ Update status
$conn->query("UPDATE payments SET payment_status='success' WHERE payment_id='$payment_id'");

// ✅ Generate PDF receipt
$pdf = new Dompdf();
$html = "
<h3>Fees Receipt</h3>
<p><strong>Student ID:</strong> {$row['student_id']}</p>
<p><strong>Name:</strong> {$row['student_name']}</p>
<p><strong>Email:</strong> {$row['email']}</p>
<p><strong>Amount:</strong>" . ($row['amount'] / 100) . "</p>
<p><strong>Payment ID:</strong> {$row['payment_id']}</p>
";
$pdf->loadHtml($html);
$pdf->render();
$receiptDir = __DIR__ . "/receipts";
if (!is_dir($receiptDir)) mkdir($receiptDir); // Create if not exists
$pdfPath = "$receiptDir/receipt_{$row['student_id']}_{$payment_id}.pdf";

file_put_contents($pdfPath, $pdf->output());

// ✅ Send Email
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'csuraj156@gmail.com';  // Replace
    $mail->Password = 'ypme mnxc bvih frah';     // Replace
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('csuraj156@gmail.com', 'School Admin');
    $mail->addAddress($row['email'], $row['student_name']);
    $mail->Subject = 'Fees Receipt';
    $mail->Body = "Dear {$row['student_name']}, your payment was successful. Please find your receipt attached.";
    $mail->addAttachment($pdfPath);
    $mail->send();
} catch (Exception $e) {
    // log error if needed
}
?>

<!-- ✅ Animated success screen with dynamic name & receipt download -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Successful</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      height: 100vh;
      background: linear-gradient(135deg, #00b09b, #96c93d);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #fff;
    }

    .success-box {
      background-color: #fff;
      color: #333;
      padding: 40px;
      max-width: 420px;
      border-radius: 20px;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
      text-align: center;
      animation: fadeSlide 0.6s ease-in-out;
    }

    @keyframes fadeSlide {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .checkmark {
      width: 90px;
      height: 90px;
      margin: 0 auto 20px;
      background-color: #4caf50;
      border-radius: 50%;
      position: relative;
    }

    .checkmark::after {
      content: '';
      position: absolute;
      left: 26px;
      top: 20px;
      width: 22px;
      height: 45px;
      border-right: 6px solid white;
      border-bottom: 6px solid white;
      transform: rotate(45deg);
    }

    h2 {
      font-size: 26px;
      margin: 10px 0;
      color: #4caf50;
    }

    p {
      font-size: 16px;
      margin-bottom: 20px;
      line-height: 1.5;
    }

    .btn {
      display: inline-block;
      background: #00b09b;
      color: white;
      padding: 12px 22px;
      text-decoration: none;
      border-radius: 10px;
      font-weight: 600;
      transition: background 0.3s ease;
      margin: 10px 5px;
    }

    .btn:hover {
      background: #028f7f;
    }

    @media (max-width: 480px) {
      .success-box {
        padding: 30px 20px;
        max-width: 90%;
      }
    }
  </style>
</head>
<body>
  <div class="success-box">
    <div class="checkmark"></div>
    <h2>✅ Payment Successful</h2>
    <p>Thank you, <strong><?= htmlspecialchars($row['student_name']) ?></strong>!<br>Your fee payment was completed successfully.</p>
    <a href="receipt_<?= $row['student_id'] ?>.pdf" class="btn" download>📥 Download Receipt</a>
    <a href="index.html" class="btn">🏠 Home</a>
  </div>
</body>
</html>

