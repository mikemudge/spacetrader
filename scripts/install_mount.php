<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();

$ship = Ship::load("MUDGE-A");
$ship->dock();
$mountSymbol ='MOUNT_MINING_LASER_I';

// TODO check if there is space to mount this before we purchase it?

echo("See script before running");
exit;
// Only buy if we don't have it already in cargo.
if ($ship->getCargo()->getAmountOf($mountSymbol) < 1) {
    // Initial price was only $9,226 (didn't increase after 1 purchase)
    // If this helps, it will be worth doing for most of my fleet?
    $transaction = $ship->purchase([
        'symbol' => $mountSymbol,
        'units' => 1
    ]);
    $transaction->describe();
} else {
    echo("Already have a $mountSymbol in cargo\n");
}

$agent->installMount($ship, $mountSymbol);
$ship->printCargo();

// TODO show all mounts?
