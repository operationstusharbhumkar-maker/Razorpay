<?php
require 'config.php';

 $url = SUPABASE_URL . '/rest/v1/customers?select=*&limit=1';

 $ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);

 $response = curl_exec($ch);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 $error = curl_error($ch);
curl_close($ch);

echo "<h3>Supabase Connection Test</h3>";
echo "HTTP Code: " . $httpCode . "<br>";

if ($error) {
    echo "<span style='color:red'>Error: " . $error . "</span>";
} elseif ($httpCode === 200) {
    echo "<span style='color:green'>✅ Connection Successful!</span>";
    echo "<pre>" . $response . "</pre>";
} else {
    echo "<span style='color:orange'>Response:</span>";
    echo "<pre>" . $response . "</pre>";
}
?>