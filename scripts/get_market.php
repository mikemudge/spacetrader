<?php
include_once "classes/autoload.php";
include_once "functions.php";

if (has_arg('--hq')) {
    $agent = new Agent();
    $marketWaypoint = $agent->getHq();
} else {
    $astroidWaypoint = "X1-HQ18-98695F";
    $marketWaypoint = new Waypoint($astroidWaypoint);
}

$market = $marketWaypoint->getMarket();
echo("\n" . json_encode($market, JSON_PRETTY_PRINT) . "\n");

echo(get_symbols($market['exchange']) . "\n");

echo("tradeGoods\n");
$tradeGoods = $market['tradeGoods'];
echo("symbol\t\t\tsellPrice\ttradeVolume\n");
foreach($tradeGoods as $item) {
    $t1 = str_repeat("\t", 3 - intval(strlen($item['symbol']) / 8));
    echo($item['symbol'] . $t1 . $item['sellPrice'] . "\t\t" . $item['tradeVolume'] . "\n");
}
