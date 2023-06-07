<?php
include_once "classes/autoload.php";
include_once "functions.php";

$miningShipSymbol = "MUDGE-3";
$ship = new Ship($miningShipSymbol);

$agent = new Agent();
$contracts = $agent->getContracts();
// Always work on the first contract.
$contract = $contracts[0];
$contractOreSymbol = $contract->getDeliver()[0]['tradeSymbol'];

$destinationSymbol = $contract->getDeliver()[0]['destinationSymbol'];

echo("Taking $contractOreSymbol to $destinationSymbol\n");

$ship->printCargo();

$cargo = $ship->getCargo();
$units = 0;
foreach ($cargo['inventory'] as $k=>$v) {
    if ($v['symbol'] == $contractOreSymbol) {
        $units = $v['units'];
        break;
    }
}

// Check if we actually have the stuff we need?
if ($units < 5) {
    echo("We only have $units $contractOreSymbol, not worth delivering?\n");
    exit(0);
}

$ship->completeNavigateTo($destinationSymbol);

// TODO this has a delay of ~194 seconds to navigate.
/*
    "error": {
        "message": "Ship is currently in-transit from X1-HQ18-98695F to X1-HQ18-93722X and arrives in 194 seconds.",
        "code": 4214,
        "data": {
            "departureSymbol": "X1-HQ18-98695F",
            "destinationSymbol": "X1-HQ18-93722X",
            "arrival": "2023-06-05T20:38:25.574Z",
            "departureTime": "2023-06-05T20:35:10.574Z",
            "secondsToArrival": 194
        }
    }
 */

$ship->dock();
$contract->deliver([
    'shipSymbol' => $ship->getId(),
    'tradeSymbol' => $contractOreSymbol,
    'units' => $units
]);
