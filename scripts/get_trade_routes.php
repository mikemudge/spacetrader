<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
// This will create all markets, and tradeGoods for markets which have ships already.
$markets = $agent->getSystemMarkets();

$routes = TradeRoute::findAll($markets);

function printRoute($ship, $route) {
    $ppu = $route->getValue();
    // Assume max capacity will be used for the route
    $cap = $ship->getCargo()->getCapacity();
    $goodsProfit = $cap * $ppu;

    // TODO fuel usage could be improved?
    $distance = $route->getDistance();
    $fuelTopup = ceil(max(1, intval($distance) / 100));
    $fuelCostBuyer = $route->getBuyer()->getBuyPrice("FUEL") * $fuelTopup;
    $fuelCostSeller = $route->getSeller()->getBuyPrice("FUEL") * $fuelTopup;

    $estValue = $ship->estimateValue($route);
    $estTime = $ship->getTimeForDistance($distance) * 2;
    // Routes which pay 0 or less are not worth running, so don't list them.
    if ($ppu > 0) {
        echo("PPU: $ppu * $cap = $goodsProfit; costs: $fuelCostBuyer, $fuelCostSeller time: $estTime estValue: " . $estValue  . " " . $route->getDescription() ."\n");
    }
}

// Fixed ship for value calculations (speed and cargo).
$ship = Ship::load("MUDGE-1");

$selectedGood = get_arg("--good");
if ($selectedGood) {
    foreach ($markets as $market) {
        $price = $market->getBuyPrice($selectedGood);
        if ($price > 0) {
            $loc = $market->getWaypointSymbol();
            echo("Found $selectedGood at $loc for $price\n");
        }
    }
} else {
    foreach ($routes as $good => $goodsRoutes) {
        /** @var TradeRoute[] $goodsRoutes */
        foreach ($goodsRoutes as $route) {
            // Routes which pay 0 or less are not worth running, so don't list them.
            if ($route->getValue() > 0) {
                printRoute($ship, $route);
            }
        }
    }
}

/*
Value: 6 MACHINERY route X1-KS52-10488F -> X1-KS52-07960X 76.06
Value: 2 FABRICS route X1-KS52-10488F -> X1-KS52-07960X 76.06
Value: 3 FUEL route X1-KS52-61262Z -> X1-KS52-07960X 0.00
Value: 3 FUEL route X1-KS52-31553B -> X1-KS52-07960X 0.00
Value: 3 FUEL route X1-KS52-25044Z -> X1-KS52-07960X 0.00
Value: 3 FUEL route X1-KS52-51225B -> X1-KS52-07960X 58.01
Value: 13 FUEL route X1-KS52-23717D -> X1-KS52-07960X 83.67
Value: 3 FUEL route X1-KS52-10488F -> X1-KS52-07960X 76.06
Value: 6 FUEL route X1-KS52-23717D -> X1-KS52-61262Z 83.67
Value: 6 FUEL route X1-KS52-23717D -> X1-KS52-31553B 83.67
Value: 6 FUEL route X1-KS52-23717D -> X1-KS52-25044Z 83.67
Value: 6 FUEL route X1-KS52-23717D -> X1-KS52-51225B 40.25
Value: 6 FUEL route X1-KS52-23717D -> X1-KS52-10488F 143.00
Value: 2 ELECTRONICS route X1-KS52-10488F -> X1-KS52-07960X 76.06
Value: 6 PLASTICS route X1-KS52-31553B -> X1-KS52-07960X 0.00
Value: 1 PLASTICS route X1-KS52-10488F -> X1-KS52-07960X 76.06
Value: 1 EQUIPMENT route X1-KS52-10488F -> X1-KS52-07960X 76.06
*/

/*
Speeds, mostly 2, except MUDGE-1 is 30.
Distances?

MUDGE-3: Travelling 58.01 X1-KS52-61262Z to X1-KS52-51225B using 58 fuel, will take 450 seconds
MUDGE-1: Travelling 76.06 X1-KS52-61262Z to X1-KS52-10488F using 76 fuel, will take 53 seconds
MUDGE-1: Travelling 0.00 X1-KS52-31553B to X1-KS52-61262Z using 1 fuel, will take 16 seconds
MUDGE-1: Travelling 83.67 X1-KS52-25044Z to X1-KS52-23717D using 84 fuel, will take 57 seconds
MUDGE-1: Travelling 83.67 X1-KS52-23717D to X1-KS52-07960X using 84 fuel, will take 58 seconds
MUDGE-1: Travelling 58.01 X1-KS52-25044Z to X1-KS52-51225B using 58 fuel, est 44, will take 44 seconds
MUDGE-6: Travelling 40.25 X1-KS52-23717D to X1-KS52-51225B using 40 fuel, est 317, will take 315 seconds
MUDGE-1: Travelling 40.25 X1-KS52-23717D to X1-KS52-51225B using 40 fuel, est 35, will take 36 seconds
15 * (1 + 40.25 / 30) = 35.125

I suspect the formula is
time = 15 * (1 + distance / speed)
53 = 15 * (1 + 76.06 / 30)
450 = 15 * ( 1 + 58.01 / 2)

Not quite for 0 distance, rounding problem?
16 = 15 * (1 + 0 / 30)
 */
