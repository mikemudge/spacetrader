<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
$type = $argv[1];
$shipyards = $agent->getSystemShipyards();

if (count($shipyards) > 1) {
    echo("Choices for shipyard aren't expected in systems currently");
    exit;
}
// TODO check if there is a ship locally?

$agent->buyShip($shipyards[0], $type);
