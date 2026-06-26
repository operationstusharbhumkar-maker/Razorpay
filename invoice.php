<?php
require 'config.php';

// Input validation
 $ref_id = filter_input(INPUT_GET, 'ref_id', FILTER_SANITIZE_STRING);
if (empty($ref_id)) {
    die('No reference ID provided');
}

// Secure API call
 $url = SUPABASE_URL . '/rest/v1/customers?ref_id=eq.' . urlencode($ref_id) . '&select=*';
 $ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY
    ],
    CURLOPT_SSL_VERIFYPEER => true,  // ✅ Enable SSL verification
    CURLOPT_TIMEOUT => 10
]);

 $response = curl_exec($ch);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 $error = curl_error($ch);
curl_close($ch);

if ($error || $httpCode !== 200) {
    die('Error fetching customer data');
}

 $result = json_decode($response, true);
if (empty($result)) {
    die('Customer not found');
}

 $c = $result[0];

// ✅ Fixed invoice number generation
 $invoiceNo = 'S-' . str_pad($c['id'] ?? substr($c['ref_id'], -4), 4, '0', STR_PAD_LEFT);

// Tax calculations
 $totalAmount = (float)$c['amount'];
 $taxableAmount = round($totalAmount / 1.18, 2);
 $cgstAmount = round($taxableAmount * 0.09, 2);
 $sgstAmount = round($taxableAmount * 0.09, 2);
 $totalTax = $cgstAmount + $sgstAmount;

// ✅ Improved number to words with decimal support
function numberToWords($num) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    if ($num == 0) return 'Zero';
    
    // Handle decimals (paisa)
    $paisa = '';
    if (strpos($num, '.') !== false) {
        $parts = explode('.', $num);
        $num = (int)$parts[0];
        $paisaVal = (int)substr($parts[1] . '00', 0, 2);
        if ($paisaVal > 0) {
            $paisa = ' and ' . convertToWords($paisaVal, $ones, $tens) . ' Paise';
        }
    } else {
        $num = (int)$num;
    }
    
    return convertToWords($num, $ones, $tens) . $paisa;
}

function convertToWords($num, $ones, $tens) {
    if ($num == 0) return '';
    
    $words = '';
    if (floor($num / 10000000) > 0) {
        $words .= convertToWords(floor($num / 10000000), $ones, $tens) . ' Crore ';
        $num %= 10000000;
    }
    if (floor($num / 100000) > 0) {
        $words .= convertToWords(floor($num / 100000), $ones, $tens) . ' Lakh ';
        $num %= 100000;
    }
    if (floor($num / 1000) > 0) {
        $words .= convertToWords(floor($num / 1000), $ones, $tens) . ' Thousand ';
        $num %= 1000;
    }
    if (floor($num / 100) > 0) {
        $words .= convertToWords(floor($num / 100), $ones, $tens) . ' Hundred ';
        $num %= 100;
    }
    if ($num > 0) {
        if ($words != '') $words .= 'and ';
        if ($num < 20) {
            $words .= $ones[$num];
        } else {
            $words .= $tens[floor($num / 10)];
            if ($num % 10 > 0) $words .= ' ' . $ones[$num % 10];
        }
    }
    return trim($words);
}

// ✅ Single function call handles paisa
 $amountInWords = 'INR ' . numberToWords(number_format($totalAmount, 2, '.', '')) . ' Only';

// Dates
 $invoiceDate = date('d-M-Y', strtotime($c['paid_at'] ?? 'now'));

if (!empty($c['batch_date'])) {
    $batchDate = date('d M. Y', strtotime($c['batch_date']));
} else {
    $batchDate = date('d M. Y', strtotime('+2 days', strtotime($c['paid_at'] ?? 'now')));
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Tax Invoice - <?php echo $invoiceNo; ?></title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:"Times New Roman", serif;
    background:#eee;
    padding:20px;
}

.invoice{
    width:800px;
    margin: 80px auto 20px auto; /* ✅ LEAVES 80px SPACE AT THE TOP */
    background:#fff;
    border:1px solid #000;
}
.invoice .logo {
    width: 10%;
}

table{
    width:100%;
    border-collapse:collapse;
}

td,th{
    border:1px solid #000;
    padding:4px;
    vertical-align:top;
    font-size:12px;
}

.no-border{
    border:none !important;
}

.center{
    text-align:center;
}

.right{
    text-align:right;
}

.bold{
    font-weight:bold;
}

.logo{
    width:75px;
}

.print-btn{
    padding:10px 20px;
    background:#000;
    color:#fff;
    border:none;
    cursor:pointer;
    margin-bottom:15px;
}

.terms{
    padding:10px;
    font-size:10px;
    line-height:1.5;
}

.terms ol{
    margin-left:18px;
}

.signature-space{
    height:70px;
}

@media print{
    .print-btn{
        display:none;
    }

    body{
        background:#fff;
        padding:0;
    }
    
    /* ✅ Ensures top space stays even when printing/saving PDF */
    .invoice {
        margin-top: 80px; 
        margin-bottom: 0;
    }
}

.terms-block{
    padding:10px;
    font-size:12px;
    line-height:1.5;
}

.terms-block .section-title{
    font-weight:bold;
    font-size:12px;
    text-transform:uppercase;
}

.terms-block em{
    display:block;
    margin:4px 0 8px 0;
    
}

.terms-block ol{
    margin-left:18px;
    padding-left:0;
}

.terms-block li{
    margin-bottom:4px;
    text-align:justify;
}
</style>
</head>

<body>

<button onclick="window.print()" class="print-btn">
Print / Save PDF
</button>

<div class="invoice">

<!-- HEADER -->
<table>

<tr>

<td style="width: 50%;">

<img src="images/tb.png" class="logo">
<br>
<b>TECHNICAL TRADE<br>
CONSULTANCY</b>
<br><br>

1372, Shukrawar Peth, Natubag,<br>
Near Kelkar Museum<br>
Pune 411002.<br>

Contact No.: 9272000111<br>

Email: info@tusharbhumkar.com<br>

Website: tusharbhumkar.com<br>

GSTIN/UIN: 27AIWPB6660M1ZK<br>

State Name: Maharashtra<br>

Code: 27

</td>

<td style="width:50%;padding:0;">

<table>

<tr>
<td class="bold">
Invoice No.<br>
<?php echo $invoiceNo; ?>
</td>

<td class="bold">
Dated<br>
<?php echo $invoiceDate; ?>
</td>
</tr>

<tr>
<td colspan="2">

<b>Buyer:</b><br>

<?php echo htmlspecialchars($c['full_name']); ?><br>

Pune<br>

Contact No.: <?php echo htmlspecialchars($c['mobile']); ?><br>

Email ID:
<?php echo htmlspecialchars($c['email']); ?><br>

Batch Dt:
<?php echo $batchDate; ?><br>

GSTIN/UIN:<br>

State Name:
Maharashtra

</td>
</tr>

</table>

</td>

</tr>

</table>

<!-- PARTICULARS -->
<table>

<tr>
    <th style="width:5%;">Sr</th>
    <th style="width:15%;">HSN/SAC</th>
    <th>Particulars</th>
    <th style="width:18%;">Amount</th>
</tr>

<tr style="height:170px;">

    <td>
        1<br><br>
        2<br>
        3
    </td>

    <td>
        999293
    </td>

    <td>

        <b>Training Charges</b>

        <div style="margin-top:25px;text-align:left;">


            <b>OUTPUT CGST @ 9%</b><br>

            <b>OUTPUT SGST @ 9%</b>

        </div>

    </td>

    <td class="right">

        <b><?php echo number_format($taxableAmount,2); ?></b>

        <br><br><br>

        <b><?php echo number_format($cgstAmount,2); ?></b>

        <br>

        <b><?php echo number_format($sgstAmount,2); ?></b>

    </td>

</tr>

<tr>

    <td colspan="3" class="right bold">
        Total
    </td>

    <td class="right bold" style="font-size:13px;">
        RS.<?php echo number_format($totalAmount,2); ?>
    </td>

</tr>

</table>

<!-- AMOUNT WORDS -->

<table>

<tr>

<td>

Amount Chargeable (in words)

<br><br>

<b>
<?php echo $amountInWords; ?>
</b>

</td>

<td class="right">

E. & O.E.

</td>

</tr>

</table>


<!-- TERMS AND CONDITIONS -->

 <table>
        <tr>
            <td class="terms-block">
                <span class="section-title">Terms and Conditions</span><br>
                <em>Please read the terms & conditions to avoid any conflict of interest.</em>
                <ol>
                    <li>We are not SEBI-registered research analysts or investment advisors.</li>
                    <li>We are only providing education services for the stock & commodity market.</li>
                    <li>All discussion and analysis in online and offline classes is just for education. We do not provide any tips, calls, buy-sell recommendations, assurance of return, guarantees on my learning techniques, investment advice, portfolio management, or account handling services.</li>
                    <li>After course completion, always conduct your own research and practice to choose securities for investment & trading. Our learning & teaching techniques do not guarantee any favourable returns, as market conditions may vary.</li>
                    <li>Investments in the securities market are subject to market risk. Read all the related documents carefully before investing.</li>
                    <li>Booking amount or fees paid towards any of our teaching and learning services are non-refundable under any conditions, and booking amount will not be transferred and refunded in any circumstances. A seat once booked can be postponed only one time in case of unavoidable circumstances.</li>
                    <li>For any queries related to our course or if you have any questions or need more information, please contact us directly.</li>
                </ol>
            </td>
        </tr>
    </table>


    
</div>

        <div style="
            text-align:center;
            font-size:11px;
            padding:10px;
            font-weight:bold;
        ">
            *** This is a Computer Generated Tax Invoice and does not require a physical signature. ***
        </div>

</body>
</html>