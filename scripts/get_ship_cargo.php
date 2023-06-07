<?php
include_once "classes/autoload.php";
include_once "functions.php";

$miningShipSymbol = "MUDGE-3";
$ship = new Ship($miningShipSymbol);

$cargo = $ship->getCargo();
echo("\n" . json_encode($cargo, JSON_PRETTY_PRINT) . "\n");

$ship->printCargo();