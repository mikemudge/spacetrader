<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = new Agent();

$agent->printInfo();

$agent->listContracts();

echo("My Ships\n");
$ships = Ship::getShips();
foreach ($ships as $ship) {
    echo ($ship->getShipDescription() . "\tfuel " . $ship->getFuelDescription() . "\tcargo " . $ship->getCargoDescription() . "\n");
}
