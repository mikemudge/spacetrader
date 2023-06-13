<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();

$ship = Ship::load("MUDGE-B");

$data = $ship->survey();
display_json($data);
$cooldown = $ship->getCooldown();
sleep($cooldown);

// cooldown and surveys are the response.
$survey = $data['surveys'][0];
// TODO find a survey we like? Or ignore?
$yield = $ship->extractOres($survey);
$cooldown = $ship->getCooldown();
echo($ship->getId() . ": Mining " . $yield['units'] . " " . $yield['symbol'] . " takes $cooldown\n");

/*
"surveys": [
        {
            "signature": "X1-KS52-51225B-93E481",
            "symbol": "X1-KS52-51225B",
            "deposits": [
                {
                    "symbol": "SILICON_CRYSTALS"
                },
                {
                    "symbol": "ICE_WATER"
                },
                {
                    "symbol": "ICE_WATER"
                },
                {
                    "symbol": "SILICON_CRYSTALS"
                },
                {
                    "symbol": "SILICON_CRYSTALS"
                }
            ],
            "expiration": "2023-06-13T07:21:19.167Z",
            "size": "MODERATE"
        }
    ]
 */