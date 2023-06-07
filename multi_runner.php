<?php
include_once "classes/autoload.php";
include_once "functions.php";

$asteroidSymbol = "X1-HQ18-98695F";

// Make sure the ship is at the asteroid for mining.
//$ship->completeNavigateTo($asteroidSymbol);

$agent = new Agent();
$contracts = $agent->getContracts();
// TODO should this be $agent->getShips() instead?
$allShips = Ship::getShips();
// Always work on the first contract.
// TODO find unfufilled ones?
$contract = $contracts[0];
$contractOreSymbol = $contract->getDeliver()[0]['tradeSymbol'];
$destinationSymbol = $contract->getDeliver()[0]['destinationSymbol'];
echo("Hoarding $contractOreSymbol for contract at $destinationSymbol\n");

$ships = [];
foreach($allShips as $s) {
    if ($s->getCargo()['capacity'] > 0) {
        $ships[] = $s;
        echo("Automating ship " . $s->getId() . "\n");
    }
}

while(true) {
    $ship = getNextAvailable($ships);
    $shipId = $ship->getId();
    echo("Using ship $shipId\n");

    // Before we start mining, list the cargo.
    $cargo = $ship->getCargo();
    if ($ship->hasCargoSpace()) {
        // TODO check if in orbit already?
        $ship->orbit();
        $yield = $ship->extractOres();
        echo("$shipId Mining " . $yield['units'] . " " . $yield['symbol'] . "\n");
    } else {
        echo("$shipId Full cargo\n");
        // Once full, list the cargo.
        $ship->printCargo();
        $ship->sellAll();
    }
}

/** @var Ship[] $ships */
function getNextAvailable(array $ships) {
    $best = -1;
    $bestShip = null;
    foreach ($ships as $ship) {
        $cooldown = $ship->getCooldown()['remainingSeconds'];
        if ($cooldown < $best || $best === -1) {
            $best = $cooldown;
            $bestShip = $ship;
        }
    }
    if ($cooldown > 0) {
        echo("Waiting $cooldown for next available ship");
        sleep($cooldown);
    }
    return $bestShip;
}
