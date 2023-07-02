<?php

class MarketService {

    private Agent $agent;
    /** @var Market[] */
    private array $markets;
    const LIMITS = [
        'REACTOR_FUSION_I' => 10,
        'MOUNT_MINING_LASER_II' => 10,
        'MODULE_ORE_REFINERY_I' => 10,
        // 100's
        'MOUNT_MINING_LASER_I' => 100,
        'FABRICS' => 100,
        'ELECTRONICS' => 100,
        'DRUGS' => 100,
        'REACTOR_SOLAR_I' => 100,
        'FOOD' => 100,
    ];
/**
"error": {
"message": "Market transaction failed. Trade good REACTOR_SOLAR_I has a limit of 100 units per transaction.",
"code": 4604,
"data": {
"waypointSymbol": "X1-YU85-34607X",
"tradeSymbol": "REACTOR_SOLAR_I",
"units": 116,
"tradeVolume": 100
}
}*/
    public function __construct(\Agent $agent) {
        $this->agent = $agent;
        $this->markets = [];
    }

    public function getSystemMarkets() {
        return $this->markets;
    }

    public function saveData(): array {
        $markets = $this->getSystemMarkets();
        $allMarketData = [];
        foreach ($markets as $market) {
            $marketData = $market->saveData();
            $allMarketData[] = $marketData;
        }
        return $allMarketData;
    }

    public function loadFrom(array $marketData) {
        // Get all the markets we can from the system.
        $waypoints = $this->agent->getSystemWaypoints();
        foreach ($waypoints as $waypoint) {
            $market = $waypoint->getMarket();
            if ($market) {
                $this->markets[] = $market;
            }
        }

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

    public function updateRates(?Ship $ship) {
        // TODO if there are multiple markets with the same age, should we use the closest? Travelling salesman?

        $markets = $this->getSystemMarkets();
        $oldest = time();
        $oldMarket = null;
        foreach ($markets as $market) {
            $lastTime = $market->getTradeGoodsTime();
            if ($oldMarket == null || $lastTime < $oldest) {
                $oldMarket = $market;
                $oldest = $lastTime;
            }
        }
        if ($oldest < strtotime("-1 hour")) {
            echo($ship->getId() . ": Refresh outdated market " . $oldMarket->getWaypointSymbol() . " at $oldest\n");
            // Older than an hour requires refresh.
            $loc = $oldMarket->getWaypointSymbol();
            if ($ship->getLocation() != $loc) {
                $ship->navigateTo($loc);
                // Return now for cooldown.
                return true;
            }
            // TODO sometimes the ship hasn't arrived yet?
            $oldMarket->updateTradeGoods();

            // Didn't technically consume its turn, but we don't want a 100 second cooldown from returning false.
            // We want this to updateRates again on the next turn.
            return true;
        }
        return false;
    }

    /** Look around the markets to see where the item can be purchased */
    public function getBestMarketFor($good): ?Market {
        $bestMarket = null;
        $bestPrice = 0;
        foreach($this->markets as $market) {
            $price = $market->getBuyPrice($good);
            if ($price) {
                if ($bestMarket == null || $price < $bestPrice) {
                    $bestMarket = $market;
                    $bestPrice = $price;
                }
            }
        }
        return $bestMarket;
    }

    /**
     * @return Transaction[] all the transactions necessary to purchase this amount of good.
     */
    public function purchase(Ship $ship, $good, $amount): array {
        $transactions = [];
        $ship->dock();
        if (isset(MarketService::LIMITS[$good])) {
            $limit =  MarketService::LIMITS[$good];
            while ($amount > $limit) {
                // Buy the limit as many times as needed, reducing the amount each time.
                $amount -= $limit;
                $data = $ship->purchase([
                    'symbol' => $good,
                    'units' => $limit
                ]);
                $transactions[] = new Transaction($data['transaction']);
            }
        }
        $data = $ship->purchase([
            'symbol' => $good,
            'units' => $amount
        ]);

        // Keep the market goods up to date.
        Waypoint::loadById($ship->getLocation())->getMarket()->updateTradeGoods();

        $this->agent->updateFromData($data['agent']);

        $transactions[] = new Transaction($data['transaction']);
        return $transactions;
    }
}