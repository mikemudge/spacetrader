<?php

class Market {

    private $waypoint;
    private $waypointId;
    private $systemSymbol;
    private $imports;
    private $exports;
    private $exchange;
    /** @var Good[] */
    private array $tradeGoods;
    private int $tradeGoodsTime;

    public function __construct(Waypoint $waypoint) {
        $this->waypoint = $waypoint;
        $this->systemSymbol = $waypoint->getSystemSymbol();
        $this->waypointId = $waypoint->getId();
        // 0 implies this has never had tradeGoods loaded.
        $this->tradeGoodsTime = 0;
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
            echo("item\t\t\t\tsell\t\tbuy\n");
            foreach ($this->tradeGoods as $tradeGood) {
                $item = $tradeGood->getId();
                $sell = number_format($tradeGood->getSellPrice());
                $buy = number_format($tradeGood->getBuyPrice());
                $t1 = tabs_for($item, 4);
                echo("$item$t1$$sell\t\t$$buy\n");
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
        // This could happen if a ship is not present, which might just be a slight timing issue?
        // Just skip if it doesn't exist, the next cycle should get it.
        if (isset($data['tradeGoods'])) {
            $this->updateTradeGoodsFromData($data['tradeGoods'], time());
        }
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
