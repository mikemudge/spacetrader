<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
$agent->getSystemWaypoints();
$contract = $agent->handleContract();
$contract->describe();

$marketSymbol = null;
if (has_arg("--buy")) {
    $marketSymbol = $agent->checkPurchases($contract);
}

// Handle delivery, if a ship is provided
$shipSymbol = get_arg("--ship");
if ($shipSymbol) {
    $ship = Ship::load($shipSymbol);
    $good = $contract->getGood();
    if ($marketSymbol) {
        $amount = $ship->getCargo()->getAmountOf($good);
        if ($ship->getCargo()->getUnits() > $amount) {
            // Selling at the location of the ship seems like a good attempt.
            echo("Selling cargo first\n");
            $ship->sellAllExcept($good);
        }
        if ($ship->getCargo()->getUnits() > $ship->getCargo()->getAmountOf($good)) {
            throw new RuntimeException($ship->getId() . ": Has non contract cargo which can't be sold\n");
        }
        $amount = min($contract->getRemaining(), $ship->getCargo()->getSpace());
        echo("Will purchase $amount of $good\n");
        $ship->completeNavigateTo($marketSymbol);
        $ship->dock();
        $ship->purchase([
            'symbol' => $contract->getGood(),
            'units' => $amount
        ]);
    }
    $contract->deliverGoods($ship);
}



