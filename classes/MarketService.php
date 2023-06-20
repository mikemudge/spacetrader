<?php

class MarketService {

    private Agent $agent;
    /** @var Market[] */
    private array $markets;

    public function __construct(\Agent $agent) {
        $this->agent = $agent;
        $this->markets = [];
        // This will fill in gaps for markets based on saved information.
        $this->loadMarkets();
    }

    public function getSystemMarkets() {
        return $this->markets;
    }

    public function saveMarkets() {
        echo("Saving market tradeGoods\n");
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
        if (!file_exists(dirname(Agent::MARKET_FILE))) {
            mkdir(dirname(Agent::MARKET_FILE));
        }
        file_put_contents(Agent::MARKET_FILE, json_encode($allMarketData, JSON_PRETTY_PRINT));
        echo("Saved\n");
    }

    private function loadMarkets() {
        // Get all the markets we can from the system.
        $waypoints = $this->agent->getSystemWaypoints();
        foreach ($waypoints as $waypoint) {
            $market = $waypoint->getMarket();
            if ($market) {
                $this->markets[] = $market;
            }
        }
        if (!file_exists(Agent::MARKET_FILE)) {
            echo("No saved market data\n");
            return;
        }
        $marketData = json_decode(file_get_contents(Agent::MARKET_FILE), true);
        // Iterate and load into the markets.
        // TODO skip loading of other systems markets?

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
        // Visit markets to keep the prices up to date.
        // TODO consider if other ships are frequenting locations?
        // Or just go to markets which haven't been visited in the longest time.
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

    public function purchase(Ship $ship, $good, $amount) {
        $ship->dock();
        $data = $ship->purchase([
            'symbol' => $good,
            'units' => $amount
        ]);

        // Keep the market goods up to date.
        Waypoint::loadById($ship->getLocation())->getMarket()->updateTradeGoods();
        $this->saveMarkets();

        $this->agent->updateFromData($data['agent']);

        return new Transaction($data['transaction']);
    }
}