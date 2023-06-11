<?php

class Market {

    private $waypoint;
    private $waypointId;
    private $systemSymbol;
    private $imports;
    private $exports;
    private $exchange;
    /** @var Good[] */
    private $tradeGoods;
    private $tradeGoodsTime;

    public function __construct(Waypoint $waypoint) {
        $this->waypoint = $waypoint;
        $this->systemSymbol = $waypoint->getSystemSymbol();
        $this->waypointId = $waypoint->getId();
    }

    public static function fromData(Waypoint $waypoint, $data) {
        $market = new Market($waypoint);
        $market->imports = $data['imports'];
        $market->exports = $data['exports'];
        $market->exchange = $data['exchange'];
        $market->tradeGoods = [];
        // These are only available when a ship is on location.
        if (isset($data['tradeGoods'])) {
            $market->updateTradeGoodsFromData($data['tradeGoods'], time());
        }
        return $market;
    }

    public function describe() {
        echo("Market at " . $this->waypoint->getDescription() . "\n");
        if (!empty($this->imports)) {
            echo("Imports:" . json_encode(get_field($this->imports, "symbol")) . "\n");
        }
        if (!empty($this->exports)) {
            echo("Exports:" . json_encode(get_field($this->exports, "symbol")) . "\n");
        }
        if (!empty($this->exchange)) {
            echo("Exchange:" . json_encode(get_field($this->exchange, "symbol")) . "\n");
        }
    }

    public function listGoods() {
        if (!empty($this->tradeGoods)) {
            echo("Trade Goods at $this->waypointId:\n");
            echo("item\t\t\tsell\tbuy\n");
            foreach ($this->tradeGoods as $tradeGood) {
                $item = $tradeGood->getId();
                $sell = $tradeGood->getSellPrice();
                $buy = $tradeGood->getBuyPrice();
                $t1 = str_repeat("\t", 3 - intval(strlen($item) / 8));
                echo("$item$t1$sell\t$buy\n");
            }
        }
    }

    public function getExports() {
        return $this->exports;
    }

    public function getImports() {
        return $this->imports;
    }

    public function getWaypoint() {
        return $this->waypoint;
    }

    public function getWaypointSymbol() {
        return $this->waypointId;
    }

    public function getExchange() {
        return $this->exchange;
    }

    public function getBuyPrice($good) {
        foreach ($this->tradeGoods as $tradeGood) {
            if ($tradeGood->getId() == $good) {
                return $tradeGood->getBuyPrice();
            }
        }
        return 0;
    }

    public function getSellPrice($good) {
        foreach ($this->tradeGoods as $tradeGood) {
            if ($tradeGood->getId() == $good) {
                return $tradeGood->getSellPrice();
            }
        }
        return 0;
    }

    public function updateTradeGoods() {
        $url = "https://api.spacetraders.io/v2/systems/$this->systemSymbol/waypoints/$this->waypointId/market";
        $json_data = get_api($url);
        $data = $json_data['data'];
        $this->updateTradeGoodsFromData($data['tradeGoods'], time());
    }

    public function getTradeGoodsTime() {
        return $this->tradeGoodsTime;
    }

    public function getTradeGoods() {
        return $this->tradeGoods;
    }

    public function updateTradeGoodsFromData(array $tradeGoods, int $time) {
        $this->tradeGoodsTime = $time;
        $this->tradeGoods = [];
        foreach ($tradeGoods as $good) {
            $this->tradeGoods[] = new Good($good);
        }
    }

    public function saveData() {
        if (empty($this->tradeGoods)) {
            // No trade goods to save.
            return null;
        }
        $goods = [];
        foreach($this->tradeGoods as $good) {
            $goods[] = $good->saveData();
        }
        return [
            "symbol" => $this->waypointId,
            "timestamp" => $this->tradeGoodsTime,
            "tradeGoods" => $goods
        ];
    }
}
