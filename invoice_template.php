$html = '
<style>
body{
font-family: DejaVu Sans;
font-size:12px;
}

.header{
text-align:center;
font-weight:bold;
}

table{
width:100%;
border-collapse:collapse;
margin-top:10px;
}

table,td,th{
border:1px solid #000;
}

td,th{
padding:8px;
}
</style>

<div class="header">
<h2>TUSHAR BHUMKAR</h2>

<p>
1372, Shukrawar Peth, Natubag,<br>
Near Kelkar Museum Pune-411002<br>

Contact No.: 9272000111<br>

Email: info@tusharbhumkar.com<br>

Website: tusharbhumkar.com<br>

GSTIN/UIN: 27AWP86660M1ZK
</p>
</div>

<h3 align="center">
TAX INVOICE
</h3>

<table>

<tr>
<td width="40%">Invoice No</td>
<td>'.$invoiceNo.'</td>
</tr>

<tr>
<td>Date</td>
<td>'.date("d-m-Y").'</td>
</tr>

<tr>
<td>Customer Name</td>
<td>'.$customer['full_name'].'</td>
</tr>

<tr>
<td>Mobile</td>
<td>'.$customer['mobile'].'</td>
</tr>

<tr>
<td>Email</td>
<td>'.$customer['email'].'</td>
</tr>

</table>

<br>

<table>

<tr>
<th>Description</th>
<th>Amount</th>
</tr>

<tr>
<td>'.$customer['course_name'].'</td>
<td>₹'.$amount.'</td>
</tr>

<tr>
<td><b>Total</b></td>
<td><b>₹'.$amount.'</b></td>
</tr>

</table>

<br><br>

<p>
Amount Paid via Razorpay
</p>

<p>
This is a computer generated invoice.
</p>

<p>
For Tushar Bhumkar
</p>
';