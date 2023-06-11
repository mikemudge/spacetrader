<?php
include_once 'functions.php';

// TODO support other callsigns/emails?
// TODO can't use our functions for this as we can't pass in a token for this call.
$ch = curl_init("https://api.spacetraders.io/v2/register");
$headers = ['Content-Type: application/json'];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, $headers);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "faction" => "COSMIC",
    "symbol" => "MUDGE",
    "email" => "mike.mudge@gmail.com"
]));

$server_output = curl_exec($ch);
curl_close($ch);

$json_data = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);
echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");

// The resulting token needs to be saved into the environment variable SPACETRADER_API_TOKEN
$token = $json_data['data']['token'];
echo("Add the following to your shell profile (~/.bash_profile or similar)\n");
echo("export SPACETRADER_API_TOKEN=\"$token\"\n");
echo("Then confirm access\n");
echo("source ~/.bash_profile\n");
echo("php scripts/get_agent.php\n");
