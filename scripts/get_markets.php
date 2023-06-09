<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = new Agent();
$hq = $agent->getHq();
$info = $hq->getInfo();
$waypoints = $hq->getSystemWaypoints();

//echo("\n" . json_encode($waypoints, JSON_PRETTY_PRINT) . "\n");

/*
    "imports": [],
    "exports": [],
    "exchange": [],
    "transactions": [],
    "tradeGoods": [],
 */

foreach ($waypoints as $waypointSymbol) {
    $waypoint = new Waypoint($waypointSymbol['symbol']);
    if ($waypoint->hasTrait('MARKETPLACE')) {
        echo($waypoint->getId() . "'s market\n");
        $market = $waypoint->getMarket();
        echo("Imports:" . json_encode(get_field($market['imports'], "symbol")) . "\n");
        echo("Exports:" . json_encode(get_field($market['exports'], "symbol")) . "\n");
        echo("Exchange:" . json_encode(get_field($market['exchange'], "symbol")) . "\n");
        // Trade goods are only available if a ship is present.
        // transactions are the same.
        if (isset($market['tradeGoods'])) {
            echo("Trade Goods:\n");
            echo("item\t\t,sell\tbuy\n");
            foreach ($market['tradeGoods'] as $tradeGood) {
                $item = $tradeGood['symbol'];
                $sell = $tradeGood['sellPrice'];
                $buy = $tradeGood['purchasePrice'];
                echo("$item\t$sell\t$buy\n");
            }
        }
    }
}
//
//echo(get_symbols($market['exchange']) . "\n");
//
//echo("tradeGoods\n");
//$tradeGoods = $market['tradeGoods'];
//echo("symbol\t\t\tsellPrice\ttradeVolume\n");
//foreach($tradeGoods as $item) {
//    $t1 = str_repeat("\t", 3 - intval(strlen($item['symbol']) / 8));
//    echo($item['symbol'] . $t1 . $item['sellPrice'] . "\t\t" . $item['tradeVolume'] . "\n");
//}
