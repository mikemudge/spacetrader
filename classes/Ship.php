<?php

class Ship {
    //FABRICATOR
    //HARVESTER
    //HAULER
    //INTERCEPTOR
    //EXCAVATOR
    //TRANSPORT
    //REPAIR
    //SURVEYOR
    //COMMAND
    //CARRIER
    //PATROL
    //SATELLITE
    //EXPLORER
    //REFINERY
    const SURVEYOR = "SURVEYOR";
    const COMMAND = "COMMAND";
    const EXCAVATOR = "EXCAVATOR";
    const REFINERY = "REFINERY";

    private $id;
    private $cargo;
    private $faction;
    private $role;
    private $fuel;
    private Waypoint $location;
    private $lastActionTime;
    private $status = null;
    private $crew;
    private $mounts;
    private $modules;
    private $engine;
    private $reactor;
    private $frame;
    private $nextActionTime;

    private function __construct($symbol) {
        $this->id = $symbol;
    }

    public static function fromData($data): Ship {
        $ship = new Ship($data['symbol']);
        $ship->updateFromData($data);
        return $ship;
    }

    public static function load(string $id): Ship {
        $url = "https://api.spacetraders.io/v2/my/ships/$id";
        $json_data = get_api($url);

        return Ship::fromData($json_data['data']);
    }

    public function getId() {
        return $this->id;
    }

    public function getShipDescription() {
        return "$this->id ($this->role)";
    }

    public function getCurrentFuel() {
        return $this->fuel['current'];
    }

    public function getFuelDescription() {
        return $this->fuel['current'] . "/" . $this->fuel['capacity'];
    }

    public function getCargo(): Cargo {
        return $this->cargo;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getRole() {
        return $this->role;
    }

    public function getSpeed() {
        return $this->engine['speed'];
    }

    public function getEngine() {
        return $this->engine;
    }

    public function getLocation(): string {
        return $this->location->getId();
    }

    public function getCargoDescription(): string {
        return $this->getCargo()->getDescription();
    }

    public function printCargo() {
        $cargo = $this->getCargo();
        echo("$this->id: Cargo " . $cargo->getDescription() . "\n");
        foreach ($cargo->getInventory() as $item) {
            echo($item['units'] . "\t" . $item['symbol'] . "\n");
        }
    }

    private function updateCargo($cargo) {
        $this->cargo = new Cargo($cargo);
    }

    /** Refresh the ship data */
    public function reload() {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id";
        $json_data = get_api($url);

        $this->updateFromData($json_data['data']);
    }

    public function updateFromData($data) {
        $this->faction = $data['registration']['factionSymbol'];
        $this->role = $data['registration']['role'];
        $this->fuel = $data['fuel'];
        $this->crew = $data['crew'];
        $this->mounts = $data['mounts'];
        $this->modules = $data['modules'];
        $this->engine = $data['engine'];
        $this->reactor = $data['reactor'];
        $this->frame = $data['frame'];
        $this->updateCargo($data['cargo']);

        // Use nav data to get location, status and time of arrival (could be future or past)
        $this->status = $data['nav']['status'];
        if ($this->getCooldown() <= 0) {
            // Only update this if we don't already have a cooldown.
            $this->nextActionTime = strtotime($data['nav']['route']['arrival']);
        }
        $this->location = Waypoint::loadById($data['nav']['waypointSymbol']);
    }

    // dock or orbit etc.
    private function performAction(string $action) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/$action";
        $json_data = post_api($url);
        $this->status = $json_data['data']['nav']['status'];
    }

    public function dock() {
        if ($this->status != "DOCKED") {
            $this->performAction('dock');
        }
    }

    public function orbit() {
        if ($this->status != "IN_ORBIT") {
            $this->performAction('orbit');
        }
    }

    public function survey() {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/survey";
        $json_data = post_api($url);

        $this->setCooldown($json_data['data']['cooldown']['remainingSeconds']);

        return $json_data['data'];
    }

    public function fuel(): ?Transaction {
        $current = $this->fuel['current'];
        $capacity = $this->fuel['capacity'];
        // TODO should consider the price, and the next destination if possible.
        if ($current >= 100 && $capacity - $current < 100) {
            // Don't need to refuel if we have more than 100 and can't topup by a full 100.
            // Some ships only have 100 capacity and need to topup.
            echo("Fuel $current/$capacity, not refuelling\n");
            return null;
        }
        echo("Fuel $current/$capacity, will refuel\n");
        $this->dock();

        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/refuel";
        $json_data = post_api($url);

        $data = $json_data['data'];
        $transaction = new Transaction($data['transaction']);
        $transaction->describe();
        return $transaction;
    }

    public function transfer(Ship $commandShip, $good, $amount) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/transfer";
        $json_data = post_api($url, [
            "tradeSymbol" => $good,
            "units" => $amount,
            "shipSymbol" => $commandShip->getId()
        ]);
        $this->updateCargo($json_data['data']['cargo']);
        // commandShip will also have a new cargo.
        $commandShip->reload();
    }

    public function purchase(array $item) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/purchase";
        $json_data = post_api($url, $item);

        // Reset cargo after something is purchased.
        $this->cargo = new Cargo($json_data['data']['cargo']);

        return new Transaction($json_data['data']['transaction']);
    }

    public function sell($item) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/sell";
        $json_data = post_api($url, $item);

        // Reset cargo after something is sold.
        $this->cargo = new Cargo($json_data['data']['cargo']);
        Agent::get()->updateFromData($json_data['data']['agent']);

        return new Transaction($json_data['data']['transaction']);
    }

    public function installMount(string $mountSymbol) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/mounts/install";
        $json_data = post_api($url, ['symbol' => $mountSymbol]);

        $this->cargo = new Cargo($json_data['data']['cargo']);
        $this->mounts = $json_data['data']['mounts'];

        return $json_data['data'];
    }

    public function waitForCooldown() {
        $cooldown = $this->getCooldown();

        if ($cooldown > 0) {
            echo("$this->id: Waiting $cooldown to cooldown\n");
            sleep($cooldown);
        }
    }

    /**
     * @throws CooldownException
     * @throws JsonException
     */
    public function extractOres($data = null) {
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/extract";

        try {
            $json_data = post_api($url, $data);

            $this->setCooldown($json_data['data']['cooldown']['remainingSeconds']);
            $this->cargo = new Cargo($json_data['data']['cargo']);

            // Show what the current extraction is.
            return $json_data['data']['extraction']['yield'];
        } catch (CooldownException $e) {
            // Update this ships next action time.
            // TODO next extract and next action are separate?
            $this->setCooldown($e->getCooldown()['remainingSeconds']);
            throw $e;
        }
    }

    public function setCooldown(int $c) {
        $this->nextActionTime = time() + $c;
    }

    public function getCooldown(): int {
        $c = $this->nextActionTime - time();
        return max($c, 0);
    }

    public function navigateTo($destinationSymbol) {
        if ($destinationSymbol == $this->location->getId()) {
//            echo("Already at $destinationSymbol, not navigating\n");
            return;
        }
        $distance = $this->location->getDistance(Waypoint::loadById($destinationSymbol));
        if ($this->fuel['capacity'] > 0) {
            $expectedFuel = $this->getFuelForDistance($distance);
            $expectedTime = $this->getTimeForDistance($distance);
            if ($this->fuel['current'] < $expectedFuel) {
                // We probably don't want to default to using this kind of fuelling.
                // More context allows for better fuel planning.
                echo("$this->id: Navigate requires fuel " . $this->getFuelDescription() . "\n");
                $this->dock();
                $this->fuel();
            } else {
                // TODO We can get to our destination, but should we fuel now or later?
                $topup = $this->fuel['capacity'] - $this->fuel['current'];
                $fuelPrice = 0;
                $market = $this->location->getMarket();
                if ($market) {
                    $fuelPrice = $market->getBuyPrice("FUEL");
                    // fuel now
                }

                echo("$this->id: Navigate could fuel at $$fuelPrice ($topup) " . $this->getFuelDescription() . "\n");
            }
        }

        // Need to be in orbit to navigate.
        $this->orbit();

        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/navigate";
        $json_data = post_api($url, ['waypointSymbol' => $destinationSymbol]);

        $data = $json_data['data'];
        $this->fuel = $data['fuel'];
        $this->status = $data['nav']['status'];
        $this->nextActionTime = strtotime($data['nav']['route']['arrival']);
        $this->location = Waypoint::loadById($data['nav']['waypointSymbol']);

        $time = $this->getCooldown();
        $expectedTime = $this->getTimeForDistance($distance);
        $consumedFuel = $this->fuel['consumed']['amount'];
        $from = $data['nav']['route']['departure']['symbol'];
        // Server time vs local time may affect the cooldown?
//        echo("now    " . date("c") . "\n");
//        echo("depart " . $data['nav']['route']['departureTime'] . "\n");
//        echo("arrive " . $data['nav']['route']['arrival'] . "\n");
        echo("$this->id: Travelling " . number_format($distance, 2) . " $from to $destinationSymbol using $consumedFuel fuel, est $expectedTime, will take $time seconds\n");
    }

    public function completeNavigateTo($destinationSymbol) {
        $this->navigateTo($destinationSymbol);
        $this->waitForCooldown();
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
        // TODO get location/market and confirm what can be sold?
        $cargo = $this->getCargo();
        $inventory = $cargo->getInventory();
        if (count($inventory) > 0) {
            // Need to dock to sell items.
            $this->dock();
        }
        foreach($inventory as $item) {
            $transaction = $this->sell($item);
            $transaction->describe();
        }
    }

    public function sellAllExcept($goods) {
        if (!is_array($goods)) {
            $goods = [$goods];
        }
        // TODO get location/market and confirm what can be sold?
        $cargo = $this->getCargo();
        $inventory = $cargo->getInventory();
        if (count($inventory) > 0) {
            // Need to dock to sell items.
            $this->dock();
        }
        $totalPrice = 0;
        foreach($inventory as $item) {
            if (in_array($item['symbol'], $goods)) {
                continue;
            }
            $transaction = $this->sell($item);
            $transaction->describe();
            $totalPrice += $transaction->getTotal();
        }
        return $totalPrice;
    }

    // Just deliver how ever much we have to this contract.
    public function deliverContract(Contract $contract) {
        $good = $contract->getGood();
        $howMuch = min($contract->getRemaining(), $this->getCargo()->getAmountOf($good));
        // We are at the dropOff location.
        $this->dock();
        // Check remaining to see if we can drop it all off, and if we should fufill it.
        $howMuch = min($howMuch, $contract->getRemaining());
        $data = $contract->deliver([
            'shipSymbol' => $this->getId(),
            'tradeSymbol' => $good,
            'units' => $howMuch
        ]);
        echo("$this->id: Delivered $howMuch $good for contract\n");
        echo($contract->getDescription() . "\n");
        $this->cargo = new Cargo($data['cargo']);

        if ($contract->getRemaining() == 0) {
            // The best time to do this is while we are already on location.
            echo("Fulfilling contract now\n");
            $contract->fulfill();
            return true;
        }
        return false;
    }

    public function clearInventory($routes) {
        if (empty($this->getCargo()->getInventory())) {
            // No inventory is the expected state.
            return;
        }

        // TODO calculate the value of selling vs jettisoning.
        echo("$this->id: Selling full inventory before starting trade routes");

        // Iterate through each cargo item, and find how to get rid of it.
        // TODO we might be able to optimize this by considering multiple items sold at the same location?
        foreach ($this->getCargo()->getInventory() as $item) {
            $good = $item['symbol'];
            echo("Choosing a route for $good because its already in cargo\n");
            // TODO consider onetime route cost vs repeated cost?
            // E.g initial cost of travel is high for a onetime route.
            $bestRoute = $routes[$good][0];
            // TODO estimate value isn't perfect as we don't have a full load.
            $bestVal = $this->estimateValue($bestRoute);
            foreach ($routes[$good] as $route) {
                $val = $this->estimateValue($route);
                if ($val > $bestVal) {
                    $bestVal = $val;
                    $bestRoute = $route;
                }
            }
            // Will go to the location and sell the item.
            $bestRoute->run($this);
        }
    }

    /*
     * An estimation on how long this ship will take to travel a distance.
     * Based on observations of distance, speed and times for ships to travel.
     */
    public function getTimeForDistance(float $dis) {
        return round(15 * (1 + $dis / $this->getSpeed()));
    }

    private function getFuelForDistance(float $distance) {
        // Always costs 1, even for 0 distance flights.
        return max(1, round($distance));
    }

    /* The value of this run in credits per minute */
    public function estimateValue(TradeRoute $route) {
        // TODO this is the worse estimate for fuel price. Assumes that the ship will topup at both ends.
        $distance = $route->getDistance();
        $fuelTopup = ceil(intval($distance) / 100);
        $fuelCost = $route->getBuyer()->getBuyPrice("FUEL") * $fuelTopup
            + $route->getSeller()->getBuyPrice("FUEL") * $fuelTopup;

        $goodsProfit = $this->getCargo()->getCapacity() * $route->getValue();
        $totalTime = $this->getTimeForDistance($distance) * 2;
        return ($goodsProfit - $fuelCost); // * 60 / $totalTime;
    }

    public function extractAndSell($hoardGood) {
        // Only extract if we have some space (Selling should keep space available).
        if ($this->getCargo()->getSpace() > 0) {
            $this->orbit();
            $yield = $this->extractOres();
            $cooldown = $this->getCooldown();
            echo("$this->id: Mining " . $yield['units'] . " " . $yield['symbol'] . " takes $cooldown\n");
        }
        if ($this->getCargo()->getSpace() <= 5) {
            echo("$this->id: Selling cargo " . $this->getCargoDescription() . " to make space\n");
            $totalPrice = $this->sellAllExcept($hoardGood);
            if ($totalPrice > 0) {
                echo("$this->id: Sold cargo "  . $this->getCargoDescription() . " for $$totalPrice\n");
            } else {
                // Nothing sold???
                $this->printCargo();
            }
        }
    }

    public function matchStatus(Ship $ship): bool {
        if ($ship->getStatus() == "DOCKED") {
            $this->dock();
            return true;
        } else if ($ship->getStatus() == "IN_ORBIT") {
            $this->orbit();
            return true;
        }
        // IN_TRANSIT isn't practical to match
        return false;
    }

    public function transferAll($hoardGood, Ship $ship) {
        if ($this->getLocation() == $ship->getLocation()) {
            $amount = $this->getCargo()->getAmountOf($hoardGood);
            // If the ship has less space, just transfer as much as we can.
            $amount = min($amount, $ship->getCargo()->getSpace());
            if ($amount > 0) {
                if ($this->matchStatus($ship)) {
                    $this->transfer($ship, $hoardGood, $amount);
                    echo("$this->id transfered $amount $hoardGood to $ship->id\n");
                }
            }
        }
    }

    public function negotiateContract() {
        $this->dock();
        $url = "https://api.spacetraders.io/v2/my/ships/$this->id/negotiate/contract";
        $json_data = post_api($url);
        return $json_data['data'];
    }

    public function hasArrivedAt(Waypoint $waypoint) {
        if ($this->getLocation() == $waypoint->getId()) {
            return $this->status != "IN_TRANSIT";
        }
        return false;
    }
}