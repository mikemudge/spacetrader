<?php
include_once "classes/autoload.php";
include_once "functions.php";

$ship = get_ship();

$waypoint = get_arg("--waypoint");
if (!$waypoint) {
    echo("--waypoint is required");
    exit();
}

$ship->navigateTo($waypoint);

$status = $ship->getStatus();
$loc = $ship->getLocation();
$ship->getFuelDescription();
$cooldown = $ship->getCooldown();
echo("Ship is $status to $loc, will take $cooldown\n");
