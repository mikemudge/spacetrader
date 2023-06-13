<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();

$waypoint = get_arg("--waypoint");
$type = get_arg('--type');
if (!$waypoint && !$type) {
    echo("--waypoint or --type is required\n");
    exit();
}

if ($type) {
    $waypoint = $agent->getWaypointsWithType($type)[0]->getId();
}
$ship = get_ship();
$ship->navigateTo($waypoint);

$status = $ship->getStatus();
$loc = $ship->getLocation();
$ship->getFuelDescription();
$cooldown = $ship->getCooldown();
// TODO already there case doesn't seem great?
echo("Ship is $status to $loc, will take $cooldown\n");
