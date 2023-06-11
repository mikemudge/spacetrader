<?php
include_once "classes/autoload.php";
include_once "functions.php";

$url = "https://api.spacetraders.io/v2/factions?limit=20";
$json_data = get_api($url);

$data = $json_data['data'];

foreach($data as $faction) {
    echo(str_pad($faction['symbol'], 10) . " recruit: " . ($faction['isRecruiting'] ? 'yes' : ' no') . " at "
        . $faction['headquarters'] . " traits: " . get_symbols($faction['traits']) . "\n");
//    echo($faction['description'] . "\n");
}
