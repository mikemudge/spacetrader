<?php

class MiningService {
    private Agent $agent;
    private Waypoint $asteroid;

    public function __construct(Agent $agent) {
        $this->agent = $agent;
        $this->asteroid = $agent->getAsteroids()[0];
    }

    public function extractAndSell(Ship $ship) {
        $contractService = $this->agent->getContractService();
        $surveyService = $this->agent->getSurveyService();
        $contractGood = $contractService->getCurrentGood();

        // Should do nothing most of the time, as the ship should already be there.
        $ship->navigateTo($this->asteroid->getId());
        if ($ship->getCooldown() > 0) {
            // If navigate did something, wait until arrival.
            return true;
        }
        if ($surveyService->perform($ship, $contractGood)) {
            // You can't survey and mine in the same turn.
            return true;
        };

        // Transfer important goods to commandShip if possible.
        $contractService->transferGoods($ship);

        // Only extract if we have some space (Selling should keep space available).
        if ($ship->getCargo()->getSpace() > 0) {
            $ship->orbit();
            $survey = $surveyService->getSurvey($ship, $contractGood);
            $data = null;
            // Survey can be returned even if contractGood is not defined.
            if ($survey && $contractGood) {
                $chance = number_format($survey->getChance($contractGood) * 100);
                $data = $survey->getData();
//                echo("$this->id: Using survey with " . $chance . "% chance of $hoardGood\n");
            }
            $yield = $ship->extractOres($data);
            $cooldown = $ship->getCooldown();
//            echo("$this->id: Mining " . $yield['units'] . " " . $yield['symbol'] . " takes $cooldown\n");
        }
        if ($ship->getCargo()->getSpace() <= 5) {
            $before = $ship->getCargo()->getUnits();
            $totalPrice = 0;
            $transactions = $ship->sellAllExcept($contractGood);
            foreach ($transactions as $transaction) {
                $totalPrice += $transaction->getTotal();
            }

            if ($totalPrice > 0) {
                $soldUnits = $before - $ship->getCargo()->getUnits();
                echo($ship->getId() . ": Sold $soldUnits cargo for $$totalPrice\n");
            } else {
                // Its not expected that this happens, log more info to help debug it.
                $ship->printCargo();
                throw new RuntimeException($ship->getId() . ": Sold nothing in extractAndSell");
            }
        }
        return true;
    }
}