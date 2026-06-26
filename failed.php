<?php
 $ref_id = $_GET['ref_id'] ?? 'N/A';
 $reason = $_GET['reason'] ?? 'unknown';

// Update Supabase
if ($ref_id !== 'N/A') {
    require 'config.php';
    
    $url = SUPABASE_URL . '/rest/v1/customers?ref_id=eq.' . urlencode($ref_id);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode([
            "payment_status" => $reason
        ]),
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
            width: 80px;
            height: 80px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }
        h1 { color: #1a1a1a; font-size: 24px; margin-bottom: 8px; }
        .subtitle { color: #888; margin-bottom: 20px; }
        .reason {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .ref-id { color: #888; font-size: 14px; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #ef4444;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 5px;
        }
        .btn:hover { background: #dc2626; }
        .btn-secondary {
            background: #6b7280;
        }
        .btn-secondary:hover { background: #4b5563; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✕</div>
        
        <h1>Payment <?php echo $reason === 'cancelled' ? 'Cancelled' : 'Failed'; ?></h1>
        <p class="subtitle">
            <?php 
            if ($reason === 'cancelled') {
                echo "You cancelled the payment process.";
            } else {
                echo "Your payment could not be processed.";
            }
            ?>
        </p>
        
        <div class="reason">
            <?php echo strtoupper($reason); ?>
        </div>
        
        <p class="ref-id">Reference: <?php echo $ref_id; ?></p>
        
        <a href="index.php" class="btn">Try Again</a>
        <a href="index.php" class="btn btn-secondary">Go Home</a>
    </div>
</body>
</html>