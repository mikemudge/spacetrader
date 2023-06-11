<?php
include_once "classes/autoload.php";
include_once "functions.php";

$url = "https://api.spacetraders.io/v2/";
$json_data = get_api($url);

echo("Status: " . $json_data['status']);
echo("Reset last: " . $json_data['resetDate'] . " next: " . $json_data['serverResets']['next']);

display_json($json_data['leaderboards']);
display_json($json_data['stats']);
// Informational things.
// $json_data['announcements']
// $json_data['links']
