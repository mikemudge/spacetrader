<?php

class Agent {
    const MARKET_FILE = "data/markets.json";
    private static Agent $agent;

    /** @var string "MUDGE" for me */
    private $id;
    private $ships;
    private $faction;
    private $headQuarters;
    private $systemSymbol;
    private $systemWaypoints;
    private $credits;
    private $contracts;

    private function __construct() {
        $this->ships = [];
    }

    public static function load() {
        $agent = new Agent();
        $json_data = get_api("https://api.spacetraders.io/v2/my/agent");
        $agent->updateFromData($json_data['data']);
        Agent::$agent = $agent;
        return $agent;
    }

    public static function get() {
        return Agent::$agent;
    }

    public function sig_handler($signo) {
        echo("Handling signal $signo\n");

        echo("Saving market tradeGoods\n");
        $this->saveMarkets();
        echo("Saved\n");
        exit;
    }

    public function updateFromData($data) {
        $this->id = $data['symbol'];
        $this->faction = $data['startingFaction'];
        $this->headQuarters = $data['headquarters'];
        $this->systemSymbol = substr($this->headQuarters, 0, strripos($this->headQuarters, "-"));
        $this->credits = $data['credits'];
        return $data;
    }

    public function newContract($ship) {
        // TODO must be at hq to get new contract, must have fufilled all existing contracts.
        $url = "https://api.spacetraders.io/v2/my/ships/" . $ship->getId() . "/negotiate/contract";
        $json_data = post_api($url);
        return Contract::create($json_data['data']);
    }

    /** @return Contract[] */
    public function getContracts(): array {
        $json_data = get_api("https://api.spacetraders.io/v2/my/contracts");

        $contractData = $json_data['data'];
        $contracts = [];
        foreach ($contractData as $data) {
            $contracts[] = Contract::create($data);
        }
        return $contracts;
    }

    public function reloadShips() {
        $url = "https://api.spacetraders.io/v2/my/ships";
        $json_data = get_api($url);
        $shipMap = [];
        foreach ($this->ships as $ship) {
            $shipMap[$ship->getId()] = $ship;
        }
        foreach ($json_data['data'] as $data) {
            $symbol = $data['symbol'];
            if (isset($shipMap[$symbol])) {
                $shipMap[$symbol]->updateFromData($data);
            } else {
                echo("A new ship was discovered during refresh $symbol\n");
                $this->ships[] = Ship::fromData($data);
            }
        }
        return $this->ships;
    }

    /**
     * @return Ship[]
     */
    public function getShips() {
        if ($this->ships) {
            return $this->ships;
        }

        $url = "https://api.spacetraders.io/v2/my/ships";
        $json_data = get_api($url);
        foreach ($json_data['data'] as $data) {
            $this->ships[] = Ship::fromData($data);
        }
        return $this->ships;
    }

    public function buyShip(Waypoint $shipyard, string $type) {
        $json_data = post_api("https://api.spacetraders.io/v2/my/ships", [
            'shipType' => $type,
            'waypointSymbol' => $shipyard->getId(),
        ]);

        $this->updateFromData($json_data['data']['agent']);
        $ship = Ship::fromData($json_data['data']['ship']);
        $this->ships[] = $ship;
        // This is not a goods Transaction, it has different fields, so just use the price.
        $price = $json_data['data']['transaction']['price'];
        echo("Purchased " . $ship->getId() . " a " . $ship->getRole() . " for $price\n");
    }

    public function getDescription() {
        return "$this->id $this->faction \$" . number_format($this->credits) . " @ $this->headQuarters";
    }

    public function listContracts() {
        $contracts = $this->getContracts();
        foreach ($contracts as $contract) {
            $contractId = $contract->getId();
            echo("Contract $contractId accepted: " . $contract->getAccepted() . " fufilled: " . $contract->getFulfilled() . "\n");
            echo($contract->getDescription() . "\n");
        }
    }

    /** Lazily load the waypoints for this system
     * @return Waypoint[]
     */
    public function getSystemWaypoints() {
        if (!$this->systemWaypoints) {
            $this->systemWaypoints = Waypoint::loadSystem($this->systemSymbol);
        }
        return $this->systemWaypoints;
    }

    public function getSystemMarkets() {
        $waypoints = $this->getSystemWaypoints();
        /** @var Market[] $markets */
        $markets = [];
        foreach ($waypoints as $waypoint) {
            $market = $waypoint->getMarket();
            if ($market) {
                $markets[] = $market;
            }
        }
        return $markets;
    }

    public function getSystemSymbol(): string {
        return $this->systemSymbol;
    }

    public function getSystemShipyards() {
        $waypoints = $this->getSystemWaypoints();
        /** @var Waypoint[] $shipyards */
        $shipyards = [];
        foreach ($waypoints as $waypoint) {
            if ($waypoint->hasTrait("SHIPYARD")) {
                $shipyards[] = $waypoint;
            }
        }
        return $shipyards;
    }

    /**
     * @return Waypoint[]
     */
    public function getAsteroids(): array {
        $waypoints = $this->getSystemWaypoints();
        $result = [];
        foreach ($waypoints as $waypoint) {
            if ($waypoint->getType() === "ASTEROID_FIELD") {
                $result[] = $waypoint;
            }
        }
        return $result;
    }

    /**
     * @param string $role
     * @return Ship[]
     */
    public function getShipsWithRole(string $role): array {
        $ships = [];
        foreach($this->getShips() as $s) {
            if ($s->getRole() === $role) {
                $ships[] = $s;
            }
        }
        return $ships;
    }

    private function saveMarkets() {
        $markets = $this->getSystemMarkets();
        $allMarketData = [];
        foreach ($markets as $market) {
            $marketData = $market->saveData();
            if ($marketData) {
                $allMarketData[] = $marketData;
                echo("Market " . $market->getWaypointSymbol() . " data from " . $market->getTradeGoodsTime() . " saved\n");
            } else {
                echo("Market " . $market->getWaypointSymbol() . " not saved\n");
            }
        }
        if (!file_exists(Agent::MARKET_FILE)) {
            mkdir(dirname(Agent::MARKET_FILE));
        }
        file_put_contents(Agent::MARKET_FILE, json_encode($allMarketData, JSON_PRETTY_PRINT));
    }

    public function loadMarkets() {
        if (!file_exists(Agent::MARKET_FILE)) {
            echo("No saved market data\n");
            return;
        }
        $marketData = json_decode(file_get_contents(Agent::MARKET_FILE), true);
        // Iterate and load into the markets.

        echo("Loading " . count($marketData) . " markets\n");
        foreach ($marketData as $market) {
            $w = Waypoint::loadById($market['symbol']);
            $m = $w->getMarket();
            // Only add data for markets which don't already have it.
            if (empty($m->getTradeGoods())) {
                $m->updateTradeGoodsFromData($market['tradeGoods'], $market['timestamp']);
            }
        }
    }

    public function getFirstContract() {
        // Always work on the first contract which is not fulfilled.
        $contracts = $this->getContracts();
        foreach ($contracts as $contract) {
            if (!$contract->getFulfilled()) {
                return $contract;
            }
        }
        return null;
    }

    public function buyMiningShip() {
        $shipyards = $this->getSystemShipyards();
        // TODO cache shipyard?
        $ships = $shipyards[0]->getShipyard()['ships'];
        $cost = 0;
        foreach ($ships as $ship) {
            if ($ship['type'] == 'SHIP_MINING_DRONE') {
                $cost = $ship['purchasePrice'];
            }
        }

        // If we can afford it
        echo("SHIP_MINING_DRONE costs: $cost, have: $this->credits\n");
        if ($this->credits > $cost) {
            // TODO check if there is a ship locally?
            $this->buyShip($shipyards[0], 'SHIP_MINING_DRONE');
            echo("SHIP_MINING_DRONE purchased\n");
        }
    }

    public function addContract(Contract $contract) {
        $this->contracts[] = $contract;
    }
}