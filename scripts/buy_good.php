<?php
include_once "classes/autoload.php";
include_once "functions.php";

$good = get_arg("--good");
$amount = intval(get_arg("--amount") ?? "0");
if (!$good) {
    echo("Usage: $argv[0] --good X\n");
    exit;
}

$agent = Agent::load();
// This will create all markets, and tradeGoods for markets which have ships already.
$markets = $agent->getMarketService()->getSystemMarkets();

// Fixed ship for value calculations (speed and cargo).
$ship = Ship::load("MUDGE-1");
$ship->printCargo();
$loc = Waypoint::loadById($ship->getLocation());

$price = $loc->getMarket()->getBuyPrice($good);
echo("Purchase $amount@$$price for $" . ($price*$amount) . "\n");
if ($amount > 0) {
    $transaction = $agent->getMarketService()->purchase($ship, $good, $amount);
    $transaction->describe();
}