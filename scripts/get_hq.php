<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = new Agent();

$waypoint = $agent->getHq();

$info = $waypoint->getInfo();

echo("Hq: " . $info['symbol'] . " " . $info['type'] . " at " . $info['x'] . "," . $info['y'] . "\n");
echo("orbitals: " . json_encode(get_field($info['orbitals'], 'symbol')) . "\n");

$waypoints = $waypoint->getSystemWaypoints();

foreach ($waypoints as $i => $waypoint) {
    $traits = $waypoint['traits'];
    $ts = [];
    foreach($traits as $item) {
        $ts[] = $item['symbol'];
    }
    echo($waypoint['symbol'] . " " . $waypoint['type'] . " with traits " . json_encode($ts) . "\n");
}
