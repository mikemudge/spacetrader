<?php
include_once "classes/autoload.php";
include_once "functions.php";

$url = "https://api.spacetraders.io/v2/";
$json_data = get_api($url);

echo("Status: " . $json_data['status'] . "\n");
echo("Reset last: " . $json_data['resetDate'] . " next: " . $json_data['serverResets']['next'] . "\n");

display_json($json_data['stats']);

// Informational things.
// $json_data['announcements']
// $json_data['links']

foreach ($json_data['leaderboards'] as $boardName => $leaders) {
    echo("Leaderboard: $boardName\n");
    foreach($leaders as $agent) {
        // Every leaderboard appears to use different keys?
        $value = $agent['credits'] ?? $agent['chartCount'] ?? "?";
        $name = $agent['symbol'] ?? $agent['agentSymbol'] ?? "?";
        echo("$value\t\t$name\n");
    }
}