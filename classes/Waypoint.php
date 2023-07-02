<?php

class Waypoint {
    public const ASTEROID_FIELD = "ASTEROID_FIELD";
    public const DEBRIS_FIELD = "DEBRIS_FIELD";
    public const GAS_GIANT = "GAS_GIANT";
    public const GRAVITY_WELL = "GRAVITY_WELL";
    public const JUMP_GATE = "JUMP_GATE";
    public const MOON = "MOON";
    public const NEBULA = "NEBULA";
    public const ORBITAL_STATION = "ORBITAL_STATION";
    public const PLANET = "PLANET";

    private static array $cachedWaypoints;
    private array $data;
    private string $id;
    private string $systemSymbol;
    private array $orbitals;
    private ?array $traits = null;
    private string $faction;
    private string $type;
    private int $x;
    private int $y;
    private Market $market;

    private function __construct(string $waypoint) {
        $this->id = $waypoint;
        // Remove the last part to get just the system id.
        $this->systemSymbol = self::toSystemSymbol($this->id);
    }

    /**
     * A way to load a single waypoints meta data.
     * More commonly we should load all waypoints in a system with a call to loadSystem()
     */
    public static function load(string $symbol) {
        $waypoint = new Waypoint($symbol);
        $url = "https://api.spacetraders.io/v2/systems/$waypoint->systemSymbol/waypoints/$waypoint->id";
        $json_data = get_api($url);
        return $json_data;
    }

    public static function fromData($waypointData) {
        $waypoint = new Waypoint($waypointData['symbol']);
        $waypoint->updateData($waypointData);
        return $waypoint;
    }

    private function updateData($data) {
        // "faction", "chart", "traits", "orbitals", "x", "y", "type"
        $this->faction = $data['faction']['symbol'];
        $this->orbitals = $data['orbitals'];
        $this->traits = $data['traits'];
        $this->faction = $data['faction']['symbol'];
        $this->type = $data['type'];
        $this->x = intval($data['x']);
        $this->y = intval($data['y']);
        $this->data = $data;
    }

    private static function loadMany($data) {
        $waypoints = [];
        foreach($data as $wData) {
            $waypoint = Waypoint::fromData($wData);
            $waypoints[] = $waypoint;
            Waypoint::$cachedWaypoints[$waypoint->getId()] = $waypoint;
        }
        return $waypoints;
    }

    public static function loadById($waypointSymbol): Waypoint {
        if (!isset(Waypoint::$cachedWaypoints[$waypointSymbol])) {
            throw new RuntimeException("Loading uncached waypoint $waypointSymbol");
        }
        return Waypoint::$cachedWaypoints[$waypointSymbol];
    }

    public function getMarket(): ?Market {
        if (!$this->hasTrait('MARKETPLACE')) {
            // No market at this waypoint.
            return null;
        }
        if (!isset($this->market)) {
            $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints/$this->id/market";
            $json_data = get_api($url);
            $this->market = Market::fromData($this, $json_data['data']);
        }
        return $this->market;
    }

    /** @return Waypoint[] */
    public static function loadSystem($systemSymbol) {
        $url = "https://api.spacetraders.io/v2/systems/$systemSymbol/waypoints";
        $json_data = get_api($url);
        return Waypoint::loadMany($json_data['data']);
    }

    public function getShipyard() {
        $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints/$this->id/shipyard";
        $json_data = get_api($url);
        return $json_data['data'];
    }

    public function getJumpGate() {
        $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints/$this->id/jump-gate";
        $json_data = get_api($url);
        return $json_data['data'];
    }

    public function getId() {
        return $this->id;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getDescription() {
        return "$this->type at $this->x,$this->y ($this->id)";
    }

    public function getTraits() {
        return $this->traits;
    }

    public function hasTrait(string $traitSymbol) {
        if ($this->traits === null) {
            throw new RuntimeException("Traits are not loaded for $this->id\n");
        }

        foreach ($this->traits as $trait) {
            if ($trait['symbol'] === $traitSymbol) {
                return true;
            }
        }
        return false;
    }

    public function getSystemSymbol() {
        return $this->systemSymbol;
    }

    public static function toSystemSymbol($id) {
        return substr($id, 0, strripos($id, "-"));
    }

    public function getDistance(Waypoint $other) {
        return sqrt(($this->x - $other->x) * ($this->x - $other->x) + ($this->y - $other->y) * ($this->y - $other->y));
    }
}