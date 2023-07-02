<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::noLoad();

$hq = $agent->getHeadQuarters();

$ship = get_ship();

$waypointSymbol = $ship->getLocation();
$systemSymbol = Waypoint::toSystemSymbol($waypointSymbol);

$system = $agent->getSystem($systemSymbol);
display_json($system->getData());

// Requires a warp drive?
// This is where HQ is.
$ship->warp("X1-YU85-99640B");
// Warp requires a lot of fuel.
/**
{
"error": {
"message": "Navigate request failed. Ship MUDGE-1 requires 103 more fuel for navigation.",
"code": 4203,
"data": {
"shipSymbol": "MUDGE-1",
"fuelRequired": 565,
"fuelAvailable": 462
}
}
}
 */
