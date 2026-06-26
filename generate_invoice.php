<?php

use Dompdf\Dompdf;

$invoiceNo =
    "S-" .
    pg_fetch_result(
        pg_query(
            $conn,
            "SELECT nextval('invoice_sequence')"
        ),
        0,
        0
    );

$html = "
<h1>Invoice</h1>

<p>Invoice No: {$invoiceNo}</p>

<p>Name:
{$customer['full_name']}</p>

<p>Mobile:
{$customer['mobile']}</p>

<p>Amount:
₹{$amount}</p>
";

$dompdf = new Dompdf();

$dompdf->loadHtml($html);

$dompdf->render();

$file =
"invoices/{$invoiceNo}.pdf";

file_put_contents(
    $file,
    $dompdf->output()
);