<?php

class FinanceService {

    private Agent $agent;
    private Waypoint $shipyard;
    private $ships;

    public function __construct(\Agent $agent) {
        $this->agent = $agent;
        $shipyards = $agent->getSystemShipyards();
        $this->shipyard = $shipyards[0];
        $this->ships = [];
    }

    public function purchaseShip(?Ship $ship): bool {
        // TODO if we need money to buy an item for a contract, save up for that?
        // Figure out what items can be mined vs purchased only?

        if (empty($this->ships)) {
            // On the first call this will update the ships and exit.
            // TODO ships should load from cache, like markets do?
            echo($ship->getId() . ": Preloading shipyard ships.\n");
            $mustNavigate = $this->updateShipyardDetails($ship);
            if ($mustNavigate) {
                return true;
            }
        }

        // TODO should probably get some more advanced logic for which ship to buy when?
        // Consider the prices etc?
        if (count($this->agent->getShips()) < 4) {
            $type = 'SHIP_MINING_DRONE';
        } else if (count($this->agent->getShips()) < 10) {
            // TODO should we consider mining drones here?
            $type ='SHIP_ORE_HOUND';
        } else {
            // We don't want any more ships, or choose new ones to explore them better.
            return false;
        }

        // Check the cost from the most recent known scan.
        $cost = $this->ships[$type]['purchasePrice'];
        $credits = $this->agent->getCredits();

        // We believe we have enough.
        if ($credits > $cost) {
            // Update ourselves with the latest info.
            // TODO should this have an age check, and only update when data is >X minutes old?
            // Could use an exception handler around buyShip instead?
            // Need to make sure the ship is at the location.
            if ($this->updateShipyardDetails($ship)) {
                return true;
            }
            $cost = $this->ships[$type]['purchasePrice'];
            echo($ship->getId() . ": Considering the price of $type $cost < $credits\n");

            // If we still have enough, proceed to purchase
            if ($credits > $cost) {
                $data = $this->agent->buyShip($this->shipyard, $type);
                // This is not a goods Transaction, it has different fields, so just use the price.
                $price = number_format($data['transaction']['price']);
                echo($ship->getId() . ": Purchased a $type ship for $$price\n");
            }
        }
        return false;
    }

    private function updateShipyardDetails(Ship $purchaserShip): bool {
        if ($purchaserShip->getLocation() != $this->shipyard->getId()) {
            $purchaserShip->navigateTo($this->shipyard->getId());
            // Wait for cooldown
            return true;
        }

        $shipyard = $this->shipyard->getShipyard();
        // This could happen if a ship is not present, which might just be a slight timing issue?
        // Seems to only affect the SATELLITE ship (MUDGE-2)?
        if (!isset($shipyard['ships'])) {
            $purchaserShip->setCooldown(1);
            return true;
        }
        foreach ($shipyard['ships'] as $ship) {
            $this->ships[$ship['type']] = $ship;
        }
        return false;
    }

    public function saveData(): array {
        return [
            'ships' => $this->ships
        ];
    }

    public function loadFrom(array $data) {
        echo("Loading " . count($data['ships']) . " purchasable ships\n");
        $this->ships = $data['ships'];
    }
}