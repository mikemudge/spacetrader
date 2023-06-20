<?php
include_once "classes/autoload.php";
include_once "functions.php";

$cargo = has_arg("--cargo");
$agent = Agent::load();
$agent->describe();
$contactService = $agent->getContractService();

$contract = $agent->getUnfulfilledContract();
echo("Have " . count($agent->getContracts()) . " contracts\n");
$contract->describe();

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
    if ($cargo) {
        $ship->printCargo();
    }
}
