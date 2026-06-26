<?php
require 'config.php';

$name = $_POST['name'];
$mobile = $_POST['mobile'];
$email = $_POST['email'];
$course = $_POST['course_name'];
$batch_date  = $_POST['batch_date'];
$amount = $_POST['amount'];

$refId = "TTC" . time();

// Insert into Supabase
$url = SUPABASE_URL . '/rest/v1/customers';

$data = [
    "ref_id" => $refId,
    "full_name" => $name,
    "mobile" => $mobile,
    "email" => $email,
    "course_name" => $course,
     "batch_date" => $batch_date,
    "amount" => $amount
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
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

?>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
var options = {
    key: "<?php echo RAZORPAY_KEY_ID; ?>",
    amount: <?php echo $amount * 100; ?>,
    currency: "INR",
    name: "Trade Tech Course",
    description: "Course Payment",
    notes: {
        ref_id: "<?php echo $refId; ?>"
    },
    handler: function(response) {
        window.location = "success.php?payment_id=" + response.razorpay_payment_id + "&ref_id=<?php echo $refId; ?>";
    },
    modal: {
        ondismiss: function() {
            window.location = "failed.php?ref_id=<?php echo $refId; ?>&reason=cancelled";
        }
    }
};

var rzp = new Razorpay(options);
rzp.on('payment.failed', function(response) {
    window.location = "failed.php?ref_id=<?php echo $refId; ?>&reason=failed";
});
rzp.open();
</script>