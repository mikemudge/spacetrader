<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
$agent->getSystemWaypoints();
$args = new ScriptArgs();

$ship = $args->getOptionalShip();

$contractService = new ContractService($agent);

if ($ship) {
    $contractService->ensureContract($ship);
    // TODO the ship might be enroute here?
} else {
    echo("Without --ship, we can only display the current contract");
}
$contract = $contractService->getCurrentContract();

if (has_arg("--buy") && $ship && $ship->getCargo()->getCapacity() > 0) {
    // Sell first.
    $good = $contract->getGood();
    $amount = $ship->getCargo()->getAmountOf($good);
    if ($ship->getCargo()->getUnits() > $amount) {
        // Selling at the location of the ship seems like a good attempt.
        echo("Selling cargo first\n");
        $ship->sellAllExcept($good);
    }
    if ($ship->getCargo()->getUnits() > $ship->getCargo()->getAmountOf($good)) {
        throw new RuntimeException($ship->getId() . ": Has non contract cargo which can't be sold\n");
    }
    $contractService->purchase($ship, $contract);
    $contract->deliverGoods($ship);
}



