<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
// This will create all markets, and tradeGoods for markets which have ships already.
$markets = $agent->getSystemMarkets();
// This will fill in gaps for markets based on saved information.
$agent->loadMarkets();

// Register the saveMarket signal handler only after we have loaded market data.
// Otherwise we could replace the saved data with incomplete data.
pcntl_async_signals(true);
pcntl_signal(SIGTERM, [$agent, "sig_handler"]);
pcntl_signal(SIGINT, [$agent, "sig_handler"]);


$routes = TradeRoute::findAll($markets);

$ship = get_ship();
$ship->printCargo();

$ship->waitForCooldown();

$ship->clearInventory($routes);

// TODO should optimize fuel topups if we know the trade route cost?
// Can buy fuel at the cheaper end, or even go elsewhere for fuel as needed?
// Could optimize travel speed as well?

while (true) {

    $unexploredMarkets = [];
    $hourAgo = time() - 60 * 60;
    foreach($markets as $market) {
        if (empty($market->getTradeGoods())) {
            $unexploredMarkets[] = $market;
        } else if ($market->getTradeGoodsTime() < $hourAgo) {
            // Data is old, consider it unexplored?
            $unexploredMarkets[] = $market;
        }
    }

    if (!empty($unexploredMarkets)) {
        // TODO should visit these markets to ensure data is up to date.
        // TODO should use the surveyor ship to do this, as its not useful most of the time.
        $market = $unexploredMarkets[0];
    }

    $bestRoute = TradeRoute::chooseBest($routes, $ship);
    if ($bestRoute == null) {
        echo("No routes are worth trading on\n");
        break;
    }

    $val = $ship->estimateValue($bestRoute);
    echo("Best Route: " . $bestRoute->getDescription() . " est value: " . $val . "\n");

    $bestRoute->run($ship);
}