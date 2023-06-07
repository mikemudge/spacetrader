<?php

class Waypoint {
    private $id;
    private $systemSymbol;
    private $orbitals;
    private $traits;
    private $faction;

    public function __construct(string $waypoint) {
        $this->id = $waypoint;
        // Remove the last part to get just the system id.
        $this->systemSymbol = substr($this->id, 0, strripos($this->id, "-"));
    }

    public function getInfo() {
        $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints/$this->id";
        $json_data = get_api($url);

        $this->orbitals = $json_data['data']['orbitals'];
        $this->traits = $json_data['data']['traits'];
        $this->faction = $json_data['data']['faction']['symbol'];

        return $json_data['data'];
    }

    public function getMarket() {
        $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints/$this->id/market";
        $json_data = get_api($url);
        return $json_data['data'];
    }

    public function getSystemWaypoints() {
        $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints";
        $json_data = get_api($url);
        return $json_data['data'];
    }

    public function getShipyard() {
        $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints/$this->id/shipyard";
        $json_data = get_api($url);
        return $json_data['data'];
    }

    public function getId() {
        return $this->id;
    }

    public function hasTrait(string $traitSymbol) {
        if (!$this->traits) {
            $this->getInfo();
        }

        foreach ($this->traits as $trait) {
            if ($trait['symbol'] === $traitSymbol) {
                return true;
            }
        }
        return false;
    }
}