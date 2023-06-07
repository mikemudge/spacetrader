<?php
include_once "classes/autoload.php";
include_once "functions.php";

$ship = get_ship();

$loc = $ship->getLocation();

echo($loc->getId() . "\n");

$info = $loc->getInfo();
echo($info['type'] . " at " . $info['x'] . "," . $info['y'] . "\n");
echo("\n" . json_encode($info, JSON_PRETTY_PRINT) . "\n");
