<?php

class Ship {
    private $id;
    private $extractCooldown = [
        'remainingSeconds' => 0
    ];
    private $cargo;
    private $faction;
    private $role;
    private $fuel;
    private $location;
    private $lastActionTime;

    public function __construct($symbol) {
        $this->id = $symbol;
    }

    /**
     * @return Ship[]
     */
    public static function getShips() {
        $url = "https://api.spacetraders.io/v2/my/ships";
        $json_data = get_api($url);
        $ships = [];
        foreach ($json_data['data'] as $data) {
            $ships[] = Ship::fromData($data);
        }
        return $ships;
    }

    private static function fromData($data) {
        $ship = new Ship($data['symbol']);
        $ship->faction = $data['registration']['factionSymbol'];
        $ship->role = $data['registration']['role'];
        $ship->fuel = $data['fuel'];
        return $ship;
    }

    public function getId() {
        return $this->id;
    }

    public function getShipDescription() {
        return "$this->id ($this->role)";
    }

    public function getFuelDescription() {
        return $this->fuel['current'] . "/" . $this->fuel['capacity'];
    }

    public function getCargoDescription() {
        $this->getCargo();
        return $this->cargo['units'] . "/" . $this->cargo['capacity'];
    }

    public function printCargo() {
        $cargo = $this->getCargo();

        echo("Ship Cargo " . $cargo['units'] . "/" . $cargo['capacity'] . "\n");
        foreach ($cargo['inventory'] as $item) {
            echo($item['units'] . "\t" . $item['symbol'] . "\n");
        }
    }

    public function loadInfo() {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id";
        $json_data = get_api($url);
        return $json_data['data'];
    }

    public function getLocation() {
        $data = $this->loadInfo();
        $locSymbol = $data['nav']['waypointSymbol'];
        $this->location = new Waypoint($locSymbol);
        return $this->location;
    }

    /**
     * @return array with inventory, units and capacity.
     * @throws JsonException
     */
    public function getCargo() {
        if (!$this->cargo) {
            $url = "https://api.spacetraders.io/v2/my/ships/$this->id/cargo";
            $json_data = get_api($url);

            $this->cargo = $json_data['data'];
        }

        return $this->cargo;
    }

    public function setCargo($cargo) {
        $this->cargo = $cargo;
    }

    public function hasCargoSpace() {
        $cargo = $this->getCargo();
        return $cargo['units'] < $cargo['capacity'];
    }

    // dock or orbit etc.
    private function performAction(string $action) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/$action";
        $json_data = post_api($url);
        $status = $json_data['data']['nav']['status'];
        echo("Ship is $status\n");
        return $json_data['data'];
    }

    public function dock() {
        return $this->performAction('dock');
    }

    public function orbit() {
        return $this->performAction('orbit');
    }

    public function survey() {
        // TODO learn about survey results?
        return $this->performAction('survey');
    }

    public function fuel() {
        // TODO 1 unit of fuel is 100 units in the tank.
        // figure out how to optimize?
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/refuel";
        $json_data = post_api($url);

        $data = $json_data['data'];

        echo("Purchased " . $data['transaction']['units'] . " fuel at $" . $data['transaction']['pricePerUnit'] . "\n");
        echo("Total $" . $data['transaction']['totalPrice'] . "\n");
        return $data;
    }
    public function sell($item) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/sell";
        $json_data = post_api($url, $item);

        // Reset cargo after something is sold.
        $this->cargo = null;
        return $json_data['data']['transaction'];
    }

    public function extractOres() {
        if ($this->extractCooldown && $this->extractCooldown['remainingSeconds'] > 0) {
            $t = $this->extractCooldown['remainingSeconds'];
            echo("Waiting $t to extract again\n");
            sleep($t);
            $this->extractCooldown = null;
        }

        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/extract";
        try {
            $json_data = post_api($url);

            $this->lastActionTime = time();
            $this->extractCooldown = $json_data['data']['cooldown'];
            $this->cargo = $json_data['data']['cargo'];

            // Show what the current extraction is.
            return $json_data['data']['extraction']['yield'];
        } catch (CooldownException $e) {
            $this->extractCooldown = $e->getCooldown();
            echo("Extraction requires cooldown\n");
            return $this->extractOres();
        }
    }
    public function getCooldown() {
        return $this->extractCooldown;
    }

    public function getCooldownSeconds() {
        if ($this->lastActionTime) {
            return $this->extractCooldown['remainingSeconds'] - (time() - $this->lastActionTime);
        } else {
            return $this->extractCooldown['remainingSeconds'];
        }
    }

    public function navigateTo($waypointSymbol) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/navigate";
        $json_data = post_api($url, ['waypointSymbol' => $waypointSymbol]);
        return $json_data['data'];
    }

    public function completeNavigateTo($destinationSymbol) {
        $data = $this->navigateTo($destinationSymbol);
        $route = $data['nav']['route'];
        $time = strtotime($route['arrival']) - strtotime($route['departureTime']);

        echo("Travelling for $time seconds\n");
        sleep($time);
    }

    /*
     * change navigation speed.
     curl --request PATCH \
 --url 'https://api.spacetraders.io/v2/my/ships/:shipSymbol/nav' \
 --header 'Authorization: Bearer INSERT_TOKEN_HERE' \
 --header 'Content-Type: application/json' \
 --data '{
    "flightMode": "CRUISE"
   }'
     */
    public function sellAll() {
        $cargo = $this->getCargo();
        if (count($cargo['inventory']) > 0) {
            // Need to dock to sell items.
            $this->dock();
        }
        foreach($cargo['inventory'] as $item) {
//        if ($item['symbol'] === $contractOreSymbol) {
//            // Don't sell the contract item
//            if ($item['units'] >= $cargo['capacity'] - 6) {
//                echo("Ready to drop off " . $item['units'] . " of " . $contractOreSymbol . " for contract\n");
//                $ship->completeNavigateTo($destinationSymbol);
//
//                $ship->dock();
//                $contract->deliver([
//                    'shipSymbol' => $ship->getId(),
//                    'tradeSymbol' => $contractOreSymbol,
//                    'units' => $item['units']
//                ]);
//                // TODO Reset cargo after something is delivered.
//                $ship->setCargo(null);
//
//                // return to the asteroid to continue mining.
//                $ship->completeNavigateTo($asteroidSymbol);
//                $ship->dock();
//                // TODO only buy fuel if we need it???
//                $ship->fuel();
//            } else {
//                echo("Not selling " . $item['units'] . " of " . $item['symbol'] . "\n");
//            }
//            continue;
//        }
            $transaction = $this->sell($item);
            echo("$this->id sold " . $transaction['units'] . " of " . $transaction['tradeSymbol'] . " for " . $transaction['totalPrice'] . "\n");
        }
    }
}