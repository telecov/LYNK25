<?php
$token = '1b55206b7827effe13b557b1a56ca402c48a81f9';

$url = 'https://dvref.com/api/v2/p25/reflectors/';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Token $token",
        "Accept: application/json",
        "User-Agent: LYNK25-Dashboard/1.2.1 (CA2RDP)"
    ],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);

if ($response === false) {
    echo "cURL ERROR: " . curl_error($ch) . PHP_EOL;
    curl_close($ch);
    exit;
}

$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: $http\n\n";
echo "RAW RESPONSE:\n";
echo $response . "\n";
