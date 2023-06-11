<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
$contract = $agent->getFirstContract();
// TODO this requires a ship at the location?
$contract->fulfill();
echo($contract->getDescription() . "\n");
