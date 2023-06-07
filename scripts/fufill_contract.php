<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = new Agent();
$contracts = $agent->getContracts();
// Always work on the first contract.
$contract = $contracts[0];

$json_data = $contract->fulfill();
echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
