<?php

class Agent {
    const SYSTEM_FILE = "data/system.json";
    private static Agent $agent;

    private MarketService $marketService;
    private MiningService $miningService;
    private ContractService $contractService;
    private FinanceService $financeService;
    private SurveyService $surveyService;

    /** @var string "MUDGE" for me */
    private $id;
    /** @var Ship[] */
    private array $ships;
    private $faction;
    private $headQuarters;
    private $systemSymbol;
    private $systemWaypoints;
    private $credits;
    private $contracts;

    private function __construct($data) {
        $this->updateFromData($data);
        $this->marketService = new MarketService($this);
        $this->miningService = new MiningService($this);
        $this->contractService = new ContractService($this);
        $this->financeService = new FinanceService($this);
        $this->surveyService = new SurveyService($this);
        $this->ships = [];
    }

    public static function load() {
        $json_data = get_api("https://api.spacetraders.io/v2/my/agent");
        $agent = new Agent($json_data['data']);
        $agent->loadAllData();
        Agent::$agent = $agent;
        return $agent;
    }

    /** Skip loadAllData which can be slow, but useful for avoiding API calls */
    public static function noLoad() {
        $json_data = get_api("https://api.spacetraders.io/v2/my/agent");
        $agent = new Agent($json_data['data']);
        Agent::$agent = $agent;
        return $agent;
    }

    public static function get() {
        return Agent::$agent;
    }

    public function saveOnExit() {
        // Register the signal handler after we have loaded.
        // Otherwise we could replace the saved data with incomplete data.
        // It may be better to manually save data when new information has been found?
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, [$this, "sig_handler"]);
        pcntl_signal(SIGINT, [$this, "sig_handler"]);
    }

    public function sig_handler($signo) {
        echo("Handling signal $signo\n");

        $this->saveAllData();
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
            $json_data = get_api("https://api.spacetraders.io/v2/my/contracts?limit=20&page=2");
            foreach ($json_data['data'] as $data) {
                $this->contracts[] = Contract::create($data);
            }
        }
        return $this->contracts;
    }

    public function update() {
        echo(date('H:i:s') . ": Reloading " . $this->getDescription() . "\n");
        // This makes it possible for ships to be purchased outside of this script.
        $this->reloadShips();
        $this->saveAllData();
    }

    public function reloadShips() {
        $firstTime = empty($this->ships);
        $limit = max(20, count($this->ships) + 2);
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
                if (!$firstTime) {
                    echo("A new ship was discovered during refresh $symbol\n");
                }
                $this->ships[] = Ship::fromData($data);
            }
        }
    }

    /**
     * @return Ship[]
     */
    public function getShips() {
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

    public function getSystem(string $systemSymbol) {
        $url = "https://api.spacetraders.io/v2/systems/$systemSymbol";
        $json_data = get_api($url);
        return new System($json_data['data']);
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

    public function getMiningService(): MiningService {
        return $this->miningService;
    }

    public function getContractService(): ContractService {
        return $this->contractService;
    }

    public function getFinanceService(): FinanceService {
        return $this->financeService;
    }

    public function getSurveyService(): SurveyService {
        return $this->surveyService;
    }

    public function loadAllData() {
        if (!file_exists(Agent::SYSTEM_FILE)) {
            echo("No saved market data\n");
            return;
        }
        echo("Loading all data from " . Agent::SYSTEM_FILE . "\n");

        $systemData = json_decode(file_get_contents(Agent::SYSTEM_FILE), true);

        $this->marketService->loadFrom($systemData['markets']);
        $this->financeService->loadFrom($systemData['finance']);
        $this->surveyService->loadFrom($systemData['surveys']);

        $this->loadShips();
    }

    private function loadShips() {
        // This will make an API call.
        $this->reloadShips();
        echo("Loaded " . count($this->ships) . " ships\n");
    }

    public function saveAllData() {
        $systemData = [
            'markets' => $this->getMarketService()->saveData(),
            'finance' => $this->getFinanceService()->saveData(),
            'surveys' => $this->getSurveyService()->saveData()
        ];

        if (!file_exists(dirname(Agent::SYSTEM_FILE))) {
            mkdir(dirname(Agent::SYSTEM_FILE));
        }
        file_put_contents(Agent::SYSTEM_FILE, json_encode($systemData, JSON_PRETTY_PRINT));
        echo("Saved all data to " . Agent::SYSTEM_FILE . "\n");
    }
}