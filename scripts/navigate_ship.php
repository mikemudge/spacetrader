<?php
include_once "classes/autoload.php";
include_once "functions.php";

$ship = get_ship();

$waypoint = get_arg("--waypoint");
if (!$waypoint) {
    echo("--waypoint is required");
    exit();
}

$data = $ship->navigateTo($waypoint);
echo("\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n");

$status = $data['nav']['status'];
$loc = $data['nav']['waypointSymbol'];
$fuel = $data['fuel'];
echo("\n" . json_encode($fuel, JSON_PRETTY_PRINT) . "\n");
echo("Ship is $status\n");
echo("Navigating to $loc\n");