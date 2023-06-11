<?php
include_once "classes/autoload.php";
include_once "functions.php";

$ship = get_ship();

$waypoint = get_arg("--waypoint");
if (!$waypoint) {
    echo("--waypoint is required");
    exit();
}

$ship->printCargo();

$ship->completeNavigateTo($waypoint);
$ship->dock();
$ship->sellAll();
