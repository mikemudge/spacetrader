<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();

$waypoints = $agent->getSystemWaypoints();
$systemId = $agent->getSystemSymbol();

$descibe = get_arg("--describe");
if ($descibe) {
    $waypointData = Waypoint::load($descibe);
    display_json($waypointData);
}

echo("System: $systemId\n");
foreach ($waypoints as $waypoint) {
    echo($waypoint->getDescription() . " with " . get_symbols($waypoint->getTraits()) . "\n");
}

/*
System: X1-KS52
PLANET at 4,-14 (X1-KS52-60401A) with ["TOXIC_ATMOSPHERE","VOLCANIC","WEAK_GRAVITY"]
PLANET at -19,-17 (X1-KS52-07960X) with ["OVERCROWDED","HIGH_TECH","BUREAUCRATIC","TEMPERATE","MARKETPLACE"]
MOON at -19,-17 (X1-KS52-61262Z) with ["BARREN","MARKETPLACE"]
MOON at -19,-17 (X1-KS52-31553B) with ["VOLCANIC","MARKETPLACE"]
MOON at -19,-17 (X1-KS52-25044Z) with ["FROZEN","MARKETPLACE"]
ASTEROID_FIELD at -20,41 (X1-KS52-51225B) with ["MINERAL_DEPOSITS","COMMON_METAL_DEPOSITS","STRIPPED","MARKETPLACE"]
GAS_GIANT at 16,59 (X1-KS52-00656X) with ["VIBRANT_AURORAS","STRONG_MAGNETOSPHERE"]
ORBITAL_STATION at 16,59 (X1-KS52-23717D) with ["MILITARY_BASE","MARKETPLACE","SHIPYARD"]
PLANET at 17,-84 (X1-KS52-10488F) with ["DRY_SEABEDS","WEAK_GRAVITY","MARKETPLACE"]
JUMP_GATE at 11,-92 (X1-KS52-51429E) with []
 */
