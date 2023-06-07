<?php
include_once "classes/autoload.php";
include_once "functions.php";

$shipyardSymbol = "X1-HQ18-60817D";

$url = "https://api.spacetraders.io/v2/my/ships";
$data = [
    'shipType' => 'SHIP_MINING_DRONE',
    'waypointSymbol' => $shipyardSymbol,
];
$json_data = post_api($url, $data);
echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
