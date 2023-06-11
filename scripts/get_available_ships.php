<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
$shipyards = $agent->getSystemShipyards();
$ships = $shipyards[0]->getShipyard()['ships'];

foreach ($ships as $i => $ship) {
    $dollars = "$" . number_format($ship['purchasePrice']);
    echo($dollars . str_repeat("\t", 2 - intval(strlen($dollars) / 8)) . $ship['type'] .  "\n");
}

//$69,258		SHIP_PROBE
//$170,712	    SHIP_ORE_HOUND
//$336,728	    SHIP_LIGHT_HAULER
//$1,731,660	SHIP_REFINING_FREIGHTER
//$86,381		SHIP_MINING_DRONE
