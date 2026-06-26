<?php
// FORCE SHOW ERRORS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!file_exists('vendor/autoload.php')) {
    die("<h1 style='color:red;text-align:center;margin-top:50px;'>❌ VENDOR FOLDER MISSING!<br><br><small>Run in terminal: <code>composer require dompdf/dompdf phpmailer/phpmailer</code></small></h1>");
}

require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use Dompdf\Dompdf;
use Dompdf\Options;

 $payment_id = $_GET['payment_id'] ?? 'N/A';
 $ref_id = $_GET['ref_id'] ?? 'N/A';

// ===== 1. VERIFY PAYMENT =====
 $isVerified = false;
 $razorpayDebug = "No API call made";

if ($payment_id === 'MANUAL_QR') {
    $isVerified = true;
    $razorpayDebug = "Manual QR Payment";
} elseif ($payment_id !== 'N/A') {
    $ch = curl_init("https://api.razorpay.com/v1/payments/" . $payment_id);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($curlError) {
        $razorpayDebug = "❌ cURL Error: " . $curlError;
    } else {
        $paymentData = json_decode($response, true);
        $razorpayDebug = "HTTP $httpCode | Status: " . ($paymentData['status'] ?? 'NONE');
        if ($httpCode === 200 && isset($paymentData['status'])) {
            $isVerified = in_array($paymentData['status'], ['captured', 'authorized']);
        }
    }
}

// ===== 2. FETCH CUSTOMER =====
 $customer = null;
if ($ref_id !== 'N/A') {
    $url = SUPABASE_URL . '/rest/v1/customers?ref_id=eq.' . urlencode($ref_id) . '&select=*';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (!empty($result)) $customer = $result[0];
}

// ===== 3. UPDATE SUPABASE =====
if ($ref_id !== 'N/A') {
    $updateData = [
        "payment_id" => $payment_id,
        "payment_status" => $isVerified ? "paid" : "unverified",
        "paid_at" => date('Y-m-d H:i:s')
    ];
    $url = SUPABASE_URL . '/rest/v1/customers?ref_id=eq.' . urlencode($ref_id);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($updateData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
            'Prefer: return=minimal'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ===== 4. SEND EMAIL WITH PDF + WHATSAPP =====
 $emailStatus = "Skipped";

if ($customer && $isVerified) {
    $emailStatus = sendInvoiceEmail($customer, $ref_id);
    sendWhatsAppInvoice($customer, $payment_id);
}

// =============================================
// EMAIL WITH HD PDF FUNCTION
// =============================================
function sendInvoiceEmail($customer, $ref_id) {
    $name = $customer['full_name'];
    $email = $customer['email'];
    $course = $customer['course_name'];
    $amount = $customer['amount'];
    $batch = !empty($customer['batch_date']) ? date('d M. Y', strtotime($customer['batch_date'])) : 'N/A';
    $invoiceNo = 'S - ' . str_pad(substr($ref_id, -5), 2, '0', STR_PAD_LEFT);

    // ✅ ADJUSTABLE SPACING
    $topSpacePx = 100;     // ✅ INCREASED TOP SPACE (was 40)
    $leftSpacePx = 30;     // Left side space
    $rightSpacePx = 30;    // Right side space

    // 1. Fetch invoice HTML
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/');
    $invoiceUrl = $baseUrl . '/invoice.php?ref_id=' . urlencode($ref_id);

    $ch = curl_init($invoiceUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
    $invoiceHtml = curl_exec($ch);
    curl_close($ch);

    if (empty($invoiceHtml)) return "Failed to fetch invoice HTML";

    // 2. Extract invoice content
    preg_match('/<div class="invoice">(.*?)<\/div>\s*<div style="/s', $invoiceHtml, $matches);
    $cleanHtml = $matches[1] ?? $invoiceHtml;

    $pdfHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><base href="'.$baseUrl.'/"><style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: "Times New Roman", serif;
        background: #fff;
        padding: 0;
        margin: 0;
    }

    /* ✅ PDF WRAPPER - Controls all spacing */
    .pdf-wrapper {
        padding: ' . $topSpacePx . 'px ' . $rightSpacePx . 'px 0 ' . $leftSpacePx . 'px;
    }

    .invoice {
        width: 100%;
        margin: 0;
        background: #fff;
        border: 1px solid #000;
    }

    .invoice .logo {
        width: 10%;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    td, th {
        border: 1px solid #000;
        padding: 4px;
        vertical-align: top;
        font-size: 12px;
    }

    .no-border {
        border: none !important;
    }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .bold {
        font-weight: bold;
    }

    .logo {
        width: 45px;
    }

    .terms {
        padding: 10px;
        font-size: 10px;
        line-height: 1.5;
    }

    .terms ol {
        margin-left: 18px;
    }

    .signature-space {
        height: 70px;
    }

    .terms-block {
        padding: 10px;
        font-size: 12px;
        line-height: 1.5;
    }

    .terms-block .section-title {
        font-weight: bold;
        font-size: 12px;
        text-transform: uppercase;
    }

    .terms-block em {
        display: block;
        margin: 4px 0 8px 0;
    }

    .terms-block ol {
        margin-left: 18px;
        padding-left: 0;
    }

    .terms-block li {
        margin-bottom: 4px;
        text-align: justify;
    }
    </style></head><body><div class="pdf-wrapper">' . $cleanHtml . '</div></body></html>';

    // 3. Generate HD PDF
    try {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('dpi', 150);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($pdfHtml);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();
    } catch (Exception $e) {
        return "PDF Error: " . $e->getMessage();
    }

    // 4. Send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Tax Invoice ' . $invoiceNo . ' | Technical Trade Consultancy';

        $mail->Body = "
<html>
<body style='font-family: Arial, sans-serif; color: #333;'>
    <p>Dear <strong>{$name}</strong>,</p>

    <p>Thank you for enrolling in the <strong>{$course}</strong> program with Technical Trade Consultancy.</p>

    <p>We are pleased to confirm receipt of your payment. Please find your tax invoice attached for your records.</p>

    <table cellpadding='8' cellspacing='0' border='1' style='border-collapse: collapse;'>
        <tr>
            <td><strong>Course</strong></td>
            <td>{$course}</td>
        </tr>
        <tr>
            <td><strong>Batch</strong></td>
            <td>{$batch}</td>
        </tr>
        <tr>
            <td><strong>Amount Paid</strong></td>
            <td>₹{$amount}</td>
        </tr>
        <tr>
            <td><strong>Invoice No.</strong></td>
            <td>{$invoiceNo}</td>
        </tr>
    </table>

    <p>Please keep this invoice for your records.</p>

    <p>If you have any questions or require assistance, please contact us.</p>

    <p>
        Regards,<br>
        <strong>Technical Trade Consultancy</strong><br>
        Phone: +91 9272000111
    </p>
</body>
</html>
";

        $mail->AltBody = "Dear {$name},

Thank you for enrolling in {$course}.

Batch: {$batch}
Amount Paid: ₹{$amount}
Invoice No.: {$invoiceNo}

Please find your tax invoice attached.

Regards,
Technical Trade Consultancy";

        $mail->addStringAttachment($pdfContent, 'Invoice_' . $invoiceNo . '.pdf', 'base64', 'application/pdf');

        $mail->send();
        return "✅ Sent with HD PDF!";
    } catch (Exception $e) {
        return "❌ " . $mail->ErrorInfo;
    }
}

// =============================================
// WHATSAPP FUNCTION
// =============================================
function sendWhatsAppInvoice($customer, $payment_id) {
    $mobile = $customer['mobile'];
    $name = $customer['full_name'];
    $course = $customer['course_name'];
    $amount = $customer['amount'];
    $ref_id = $customer['ref_id'];
    $batch = !empty($customer['batch_date']) ? date('d M. Y', strtotime($customer['batch_date'])) : 'TBD';
    
    $mobile = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($mobile) === 10) $mobile = '91' . $mobile;
    
    $ch = curl_init("https://api.interakt.ai/v1/public/message/");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "campaignId" => "",
            "phoneNumber" => $mobile,
            "callbackData" => $ref_id,
            "parameters" => [
                ["name" => "name", "value" => $name],
                ["name" => "course", "value" => $course],
                ["name" => "amount", "value" => "₹" . $amount],
                ["name" => "payment_id", "value" => $payment_id],
                ["name" => "ref_id", "value" => $ref_id],
                ["name" => "batch", "value" => $batch]
            ]
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . INTERAKT_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    curl_close($ch);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .icon {
            width: 80px; height: 80px; background: #22c55e; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;
        }
        .icon svg { width: 40px; height: 40px; fill: white; }
        h1 { color: #1a1a1a; font-size: 24px; margin-bottom: 8px; }
        .subtitle { color: #888; margin-bottom: 30px; }
        .details {
            background: #f8f9fa; border-radius: 10px; padding: 20px;
            margin-bottom: 20px; text-align: left;
        }
        .detail-row {
            display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #888; font-size: 14px; }
        .detail-value { color: #333; font-weight: 600; font-size: 14px; }
        .verified { color: #22c55e; }
        .unverified { color: #f59e0b; }
        .btn {
            display: inline-block; padding: 12px 30px; background: #667eea; color: white;
            text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 10px;
        }
        .btn:hover { background: #5a6fd6; }
        .debug-box {
            background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
            padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;
            font-family: monospace; text-align: left; word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
        
        <h1>Payment Successful! 🎉</h1>
        
        <div class="debug-box">
            📧 Email: <b><?php echo $emailStatus; ?></b><br>
            🔍 Payment: <b><?php echo $razorpayDebug; ?></b>
        </div>
        
        <div class="details">
            <div class="detail-row">
                <span class="detail-label">Payment ID</span>
                <span class="detail-value"><?php echo $payment_id; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Reference ID</span>
                <span class="detail-value"><?php echo $ref_id; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Name</span>
                <span class="detail-value"><?php echo $customer['full_name'] ?? 'N/A'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Course</span>
                <span class="detail-value"><?php echo $customer['course_name'] ?? 'N/A'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Batch</span>
                <span class="detail-value"><?php echo !empty($customer['batch_date']) ? date('d M. Y', strtotime($customer['batch_date'])) : 'N/A'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount</span>
                <span class="detail-value">₹<?php echo $customer['amount'] ?? 'N/A'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Verification</span>
                <span class="detail-value <?php echo $isVerified ? 'verified' : 'unverified'; ?>">
                    <?php echo $isVerified ? '✅ Verified' : '⚠️ Unverified'; ?>
                </span>
            </div>
        </div>
        <a href="invoice.php?ref_id=<?php echo $ref_id; ?>" class="btn" style="background:#22c55e; margin-right:10px;">📄 View Invoice</a>
        <a href="index.html" class="btn">← Back to Home</a>
    </div>
</body>
</html>