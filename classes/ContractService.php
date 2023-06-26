<?php

class ContractService {

    private Agent $agent;
    private string $contractLocation;

    public function __construct(Agent $agent) {
        $this->agent = $agent;
        $this->contractLocation = $this->agent->getHeadQuarters();
    }

    public function deliverContract(Ship $ship) {
        $goal = $this->getCurrentContract();
        if (!$goal) {
            // Can't do anything if there isn't a contract to deliver.
            return false;
        }
        // The good we need to deliver.
        $contractGood = $goal->getGood();
        // Check how much the ship currently has of it.
        $howMuch = $ship->getCargo()->getAmountOf($contractGood);

        if (!in_array($contractGood, SurveyService::MINABLE)) {
            // Lose your cargo to maximize carry ability.
            $ship->sellAllExcept($contractGood);
            $howMuch = $ship->getCargo()->getAmountOf($contractGood);
            if ($howMuch == 0) {
                if ($this->purchase($ship, $goal)) {
                    return true;
                }
            }
        }

        // If we are close to capacity, then we should deliver it.
        // TODO optimize when we deliver?
        // E.g is 51/60 good? 191/200? 21/30? How long does delivery take?
        // What if the contract only needs 10 more?
        if ($howMuch >= $goal->getRemaining() || $howMuch > $ship->getCargo()->getCapacity() - 10) {
            if ($ship->getLocation() != $goal->getLocation()) {
                echo($ship->getId() . ": Need to travel to deliver $howMuch $contractGood for contract\n");
                $ship->navigateTo($goal->getLocation());
                return true;
            }

            $ship->deliverContract($goal);
            if ($this->fulfillContract($ship, $goal)) {
                // TODO this assumes that new contracts come from the same location as the last.
                $this->ensureContract($ship);
            }
            // While this doesn't technically consume the turn, we want the next turn to check the contract again.
            // Otherwise the ship will navigate to an asteroid field to start mining.
            return true;
        }
        return false;
    }

    public function getCurrentGood(): ?string {
        $goal = $this->getCurrentContract();
        if ($goal) {
            return $goal->getGood();
        }
        // No current good.
        return null;
    }

    private function fulfillContract(Ship $ship,Contract $contract) {
        if ($contract->getRemaining() == 0) {
            // TODO this assumes that a ship is on location
            $data = $contract->fulfill();
            $this->agent->updateFromData($data['agent']);

            $fulfillFee = $contract->getPayment()['onFulfilled'];
            echo($ship->getId() . ": Fulfilled a contract for $$fulfillFee\n");
            return true;
        }
        return false;
    }

    /**
     * @param Ship $ship
     * @return bool Whether the ships turn was consumed by some action.
     */
    public function ensureContract(Ship $ship): bool {
        $contract = $this->getCurrentContract();
        if (!$contract) {
            // There is no contract currently, we will need to negotiate one.
            // We may need to travel to do so.
            if ($ship->getLocation() != $this->contractLocation) {
                $ship->navigateTo($this->contractLocation);
                // Return now for cooldown.
                return true;
            }
            $data = $ship->negotiateContract();
            $contract = Contract::create($data['contract']);
            $this->agent->addContract($contract);
        }
        if (!$contract->getAccepted()) {
            // Currently all contracts are accepted immediately.
            $data = $contract->accept();
            $acceptFee = number_format($contract->getPayment()['onAccepted']);
            echo($ship->getId() . ": Negotiated and accepted a contract for $$acceptFee\n");
            $this->agent->updateFromData($data['agent']);
        }
        return false;
    }

    public function getCurrentContract(): ?Contract {
        // For now always check the first unfulfilled contract.
        // TODO this should be caching the contracts?
        $contracts = $this->agent->getContracts();
        foreach ($contracts as $contract) {
            if (!$contract->getFulfilled()) {
                return $contract;
            }
        }
        return null;
    }

    public function purchase(Ship $ship, Contract $contract): bool {
        // Find good for sale and deliver it?
        // TODO this will purchase a cargo load as soon as it can.
        // It would be more efficient to deliver multiple cargo loads at a time.
        // Otherwise the ship will return to the asteroid between deliveries.
        $good = $contract->getGood();
        $amountInCargo = $ship->getCargo()->getAmountOf($good);
        $amount = min($contract->getRemaining() - $amountInCargo, $ship->getCargo()->getSpace());

        if ($amount == 0) {
            // Unable to actually purchase anything, just return to caller.
            return false;
        }
        $marketService = $this->agent->getMarketService();
        $market = $marketService->getBestMarketFor($good);
        if (!$market) {
            echo($ship->getId() . ": No market found for $good\n");
            return false;
        }
        $price = $market->getBuyPrice($good);
        $total = $price * $amount;
        $credits = $this->agent->getCredits();

        echo($ship->getId() . ": Checking market " . $market->getWaypointSymbol() . " to purchase $amount $good @ $$price for $total, we have $credits\n");
        if ($total > $credits) {
            $need = $total - $credits;
            // Need more money to perform this purchase.
            // TODO should we wait around, or go back and mine more?
            // Currently return to mining.
            // Consider cost of travel and how long it takes to travel vs how long the wait would be?
            // The wait can be based on how much credits we need, and how fast we make those?
            if ($need < 1500) {
                // Just wait, we expect to make this much quite quickly.
                // Wait 10 seconds and try again.
                $ship->setCooldown(10);
                return true;
            }
            return false;
        }
        if ($ship->getLocation() != $market->getWaypointSymbol()) {
            echo($ship->getId() . ": Going to the market to purchase $amount of $good\n");
            $ship->navigateTo($market->getWaypointSymbol());
            return true;
        }
        if ($amount > 0) {
            $transactions = $marketService->purchase($ship, $contract->getGood(), $amount);
            $this->listTransactions($ship, $transactions);
        }
        return false;
    }

    private function listTransactions(Ship $ship, array $transactions) {
        /** @var Transaction $t */
        foreach($transactions as $t) {
            echo($ship->getId() . ": " . $t->getDescription() . "\n");
        }
    }
}