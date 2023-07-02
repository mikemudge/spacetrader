<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::noLoad();

$hq = $agent->getHeadQuarters();

$ship = get_ship();

$waypointSymbol = $ship->getLocation();
$systemSymbol = Waypoint::toSystemSymbol($waypointSymbol);

$shipsSystem = $agent->getSystem($systemSymbol);
$jumpSymbol = $shipsSystem->getWaypointWithType("JUMP_GATE");

$url = "https://api.spacetraders.io/v2/systems/$systemSymbol/waypoints/$jumpSymbol/jump-gate";
$json_data = get_api($url);
$jumpGate = $json_data['data'];
display_json($jumpGate);
// Seems to be 2000 at most?
$range = $jumpGate['jumpRange'];
$nearbySystems = $jumpGate['connectedSystems'];

echo("HQ is at $hq\n");
echo("Ship system " . $shipsSystem->getId() . "\n");
echo("Ships local jump gate is " . $jumpSymbol . "\n");
echo("Nearby systems are:\n");
foreach($nearbySystems as $system) {
    echo($system['symbol'] . " " . $system['type'] . " @" . $system['x'] . "," . $system['y'] . " dis:" . $system['distance'] . "\n");
}

// Go to the jump gate so we can jump.
$ship->completeNavigateTo($jumpSymbol);
// Any ship can jump if they are at a jump gate.
if ($shipsSystem->getId() == "X1-DD46") {
    $ship->jump("X1-YU85");
} else {
    $ship->jump("X1-DD46");
}
