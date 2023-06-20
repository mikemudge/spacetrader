<?php

class Agent {
    const MARKET_FILE = "data/markets.json";
    private static Agent $agent;

    private MarketService $marketService;
    private ContractService $contractService;

    /** @var string "MUDGE" for me */
    private $id;
    private $ships;
    private $faction;
    private $headQuarters;
    private $systemSymbol;
    private $systemWaypoints;
    private $credits;
    private $contracts;

    private function __construct($data) {
        $this->updateFromData($data);
        $this->marketService = new MarketService($this);
        $this->contractService = new ContractService($this);
        $this->ships = [];
    }

    public static function load() {
        $json_data = get_api("https://api.spacetraders.io/v2/my/agent");
        $agent = new Agent($json_data['data']);
        Agent::$agent = $agent;
        return $agent;
    }

    public static function get() {
        return Agent::$agent;
    }

    public function saveMarketsOnExit() {
        // Register the saveMarket signal handler only after we have loaded market data.
        // Otherwise we could replace the saved data with incomplete data.
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, [$this, "sig_handler"]);
        pcntl_signal(SIGINT, [$this, "sig_handler"]);
    }

    public function sig_handler($signo) {
        echo("Handling signal $signo\n");

        $this->getMarketService()->saveMarkets();
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

    public function addContract(Contract $contract) {
        $this->contracts[] = $contract;
    }

    /** @return Contract[] */
    public function getContracts(): array {
        if (!$this->contracts) {
            $json_data = get_api("https://api.spacetraders.io/v2/my/contracts");
            foreach ($json_data['data'] as $data) {
                $this->contracts[] = Contract::create($data);
            }
        }
        return $this->contracts;
    }

    public function reloadShips() {
        $limit = count($this->ships) + 2;
        // TODO better paginate? But it costs API calls?
        $url = "https://api.spacetraders.io/v2/my/ships?limit=$limit";
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

        $url = "https://api.spacetraders.io/v2/my/ships?limit=20";
        $json_data = get_api($url);
        $meta = $json_data['meta'];
        // has "total": 11, "page": 1, "limit": 20
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
        return $json_data['data'];
    }

    public function getDescription() {
        return "$this->id $this->faction \$" . number_format($this->credits) . " @ $this->headQuarters";
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
        return $this->getMarketService()->getSystemMarkets();
    }

    public function getCredits(): int {
        return $this->credits;
    }

    public function getSystemSymbol(): string {
        return $this->systemSymbol;
    }

    public function getHeadQuarters() {
        return $this->headQuarters;
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

    public function getAsteroids(): array {
        return $this->getWaypointsWithType(Waypoint::ASTEROID_FIELD);
    }

    public function getOrbitalStations(): array {
        return $this->getWaypointsWithType(Waypoint::ORBITAL_STATION);
    }

    /**
     * @return Waypoint[]
     */
    public function getWaypointsWithType($type): array {
        $waypoints = $this->getSystemWaypoints();
        $result = [];
        foreach ($waypoints as $waypoint) {
            if ($waypoint->getType() === $type) {
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

    /** @Deprecated */
    public function getUnfulfilledContract(): ?Contract {
        $contracts = $this->getContracts();
        foreach ($contracts as $contract) {
            if (!$contract->getFulfilled()) {
                return $contract;
            }
        }
        return null;
    }

    public function installMount(Ship $ship, string $mountSymbol) {
        $data = $ship->installMount($mountSymbol);

        $this->updateFromData($data['agent']);

        display_json($data['transaction']);
        echo("Installed " . $mountSymbol . " for ?\n");
    }

    public function describe() {
        echo($this->getDescription() ."\n");
    }

    public function getMarketService(): MarketService {
        return $this->marketService;
    }

    public function getContractService(): ContractService {
        return $this->contractService;
    }
}