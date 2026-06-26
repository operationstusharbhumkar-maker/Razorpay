<?php
require 'config.php';

$payload = file_get_contents("php://input");

$data = json_decode($payload, true);

$payment =
    $data['payload']['payment']['entity'];

$paymentId = $payment['id'];

$amount = $payment['amount'] / 100;

$refId = $payment['notes']['ref_id'];

$customer = pg_fetch_assoc(
    pg_query_params(
        $conn,
        "SELECT * FROM customers
         WHERE ref_id = $1",
        [$refId]
    )
);

pg_query_params(
    $conn,
    "INSERT INTO payments
    (
        customer_id,
        provider,
        payment_id,
        amount,
        status
    )
    VALUES
    (
        $1,
        'RAZORPAY',
        $2,
        $3,
        'SUCCESS'
    )",
    [
        $customer['id'],
        $paymentId,
        $amount
    ]
);

include 'generate_invoice.php';

echo "OK";
?>