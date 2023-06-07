<?php
include_once "classes/autoload.php";
include_once "functions.php";

$shipyardWaypoint = new Waypoint("X1-HQ18-60817D");
$ships = $shipyardWaypoint->getShipyard()['ships'];

foreach ($ships as $i => $ship) {
    echo($ship['type'] . " $" . number_format($ship['purchasePrice']) . "\n");
}

//SHIP_PROBE 68584
//SHIP_MINING_DRONE 84695
//SHIP_LIGHT_HAULER 329338
//SHIP_REFINING_FREIGHTER 1660980
//SHIP_ORE_HOUND 214775