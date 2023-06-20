<?php
include_once "classes/autoload.php";
include_once "functions.php";

Agent::load();
$ship = get_ship();

$waypoint = get_arg("--waypoint");
if (!$waypoint) {
    $waypoint = $ship->getLocation();
}
$except = get_arg("--except");
$goods = [];
if ($except) {
    $goods = explode(",", get_arg("--except"));
}

$ship->printCargo();

$ship->completeNavigateTo($waypoint);
$ship->dock();
$transactions = $ship->sellAllExcept($goods);
foreach ($transactions as $transaction) {
    $transaction->describe();
}

$ship->printCargo();
