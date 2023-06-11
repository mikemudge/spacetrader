<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();

echo($agent->getDescription() ."\n");

$agent->listContracts();

$waypoints = $agent->getSystemWaypoints();
$waypointMap = [];
foreach ($waypoints as $waypoint) {
    $waypointMap[$waypoint->getId()] = $waypoint;
}

echo("My Ships\n");
$ships = $agent->getShips();
foreach ($ships as $ship) {
    $waypoint = Waypoint::loadById($ship->getLocation());
    echo ($ship->getShipDescription() . "\t fuel " . $ship->getFuelDescription() . "\t cargo " . $ship->getCargoDescription()
        . "\t located at " . $waypoint->getDescription() . "\n");
}
