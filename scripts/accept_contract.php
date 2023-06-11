<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
$contracts = $agent->getContracts();

foreach($contracts as $contract) {
    if ($contract->getFulfilled()) {
        // Skip already fulfilled contracts
        continue;
    }
    if ($contract->getAccepted()) {
        echo("We already have an accepted contract\n");
        echo($contract->getDescription() . "\n");
        exit;
    } else {
        // $contract was not accepted yet?
        // TODO need a ship on location for this?
        echo("Accepting contract\n");
        echo($contract->getDescription() . "\n");
        $contract->accept();
        exit;
    }
}

if ($contracts[0]->getAccepted()) {
    $ship = Ship::load("MUDGE-2");

    $ship->negotiateContract($agent);
    exit;
}
$json_data = $contracts[0]->accept();
echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
