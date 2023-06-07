<?php
include_once 'functions.php';

$contractId = get_arg("--contract");
echo("$contractId\n");
$url = "https://api.spacetraders.io/v2/my/contracts/$contractId/accept";
$ch = curl_init($url);

$token = getenv("SPACETRADER_API_TOKEN");
$headers = [
    'Authorization: Bearer ' . $token
];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, $headers);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);

$server_output = curl_exec($ch);
curl_close($ch);

$json_data = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);
echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
