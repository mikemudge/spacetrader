<?php

class TradeRoute {

    private $good;
    private $buyPoint;
    private $sellPoint;
    private $distance;

    public function __construct($good, Market $buyPoint, Market $sellPoint) {
        $this->good = $good;
        $this->buyPoint = $buyPoint;
        $this->sellPoint = $sellPoint;
        $this->distance = $this->buyPoint->getWaypoint()->getDistance($this->sellPoint->getWaypoint());
    }

    public static function create($good, Market $buyPoint, Market $sellPoint) {
        return new TradeRoute($good, $buyPoint, $sellPoint);
    }

    /**
     * @param Market[] $markets The markets to consider
     * @return array A map of good to the routes which could trade it.
     */
    public static function findAll(array $markets) {
        $goods = [];
        foreach ($markets as $market) {
            foreach($market->getExports() as $export) {
                $good = $export['symbol'];
                if (!array_key_exists($good, $goods)) {
                    $goods[$good] = [
                        'buy' => [],
                        'sell' => [],
                    ];
                }
                $goods[$good]['buy'][] = $market;
            }
            foreach($market->getImports() as $import) {
                $good = $import['symbol'];
                if (!array_key_exists($good, $goods)) {
                    $goods[$good] = [
                        'buy' => [],
                        'sell' => [],
                    ];
                }
                $goods[$good]['sell'][] = $market;
            }
            foreach($market->getExchange() as $exchange) {
                $good = $exchange['symbol'];
                if (!array_key_exists($good, $goods)) {
                    $goods[$good] = [
                        'buy' => [],
                        'sell' => [],
                    ];
                }
                // exchange goods can be bought or sold?
                $goods[$good]['buy'][] = $market;
                $goods[$good]['sell'][] = $market;
            }
        }

        $routes = [];
        foreach($goods as $good => $markets) {
            $goodsRoutes = [];
            foreach ($markets['sell'] as $seller) {
                foreach ($markets['buy'] as $buyer) {
                    if ($seller === $buyer) {
                        // It's not expected that buying and selling at the same market will be profitable.
                        // TODO verify this?
                        continue;
                    }
                    $goodsRoutes[] = TradeRoute::create($good, $buyer, $seller);
                }
            }
            if (!empty($goodsRoutes)) {
                $routes[$good] = $goodsRoutes;
            }
        }
        return $routes;
    }

    public static function chooseBest(array $routes, Ship $ship): ?TradeRoute {
        $bestRoute = null;
        $bestVal = 0;

        foreach ($routes as $goodsRoutes) {
            /** @var TradeRoute[] $goodsRoutes */
            foreach($goodsRoutes as $route) {
                $val = $ship->estimateValue($route);
                if ($val > $bestVal) {
                    $bestVal = $val;
                    $bestRoute = $route;
                }
            }
        }
        return $bestRoute;
    }

    public function getValue() {
        $buyPrice = $this->buyPoint->getBuyPrice($this->good);
        $sellPrice = $this->sellPoint->getSellPrice($this->good);

        if ($sellPrice === 0 || $buyPrice === 0) {
            // The value is unknown without prices.
            return 0;
        }
        return $sellPrice - $buyPrice;
    }

    public function getDistance() {
        return $this->distance;
    }

    public function getBuyer(): Market {
        return $this->buyPoint;
    }

    public function getSeller(): Market {
        return $this->sellPoint;
    }

    public function run(Ship $ship) {
        $actualValue = 0;
        $buy = $this->buyPoint->getWaypointSymbol();
        $sell = $this->sellPoint->getWaypointSymbol();
        $sellPrice = $this->sellPoint->getSellPrice($this->good);

        $goodInCargo = $ship->getCargo()->getAmountOf($this->good);
        if (!$goodInCargo) {
            // Then go and buy more?
            $ship->completeNavigateTo($buy);
            $ship->dock();
            // Fuel up as late as possible.
            // Getting 1050 is better than getting 150 when you pay per 100.
            if ($ship->getCurrentFuel() < 200) {
                $t = $ship->fuel();
                if ($t) {
                    $actualValue -= $t->getTotal();
                }
            }

            $this->buyPoint->updateTradeGoods();
            $buyPrice = $this->buyPoint->getBuyPrice($this->good);
            // If we don't know how much we can sell it for, its not worth taking any.
            $amount = 0;
            if ($sellPrice) {
                // If we know the sell price we can reevaluate the value now that we have the buy price.
                if ($sellPrice > $buyPrice) {
                    $amount = $ship->getCargo()->getSpace();
                }
            }
            echo("Buying $amount of $this->good at $buy\n");
            if ($amount > 0) {
                $transaction = $ship->purchase([
                    'symbol' => $this->good,
                    'units' => $amount
                ]);
                $transaction->describe();
                $actualValue -= $transaction->getTotal();
            }
        } else {
            $amount = $goodInCargo;
        }

        echo("Will be selling $amount of $this->good at $sell for ($sellPrice * $amount) = " . ($sellPrice * $amount) . "\n");
        $ship->completeNavigateTo($sell);
        $ship->dock();
        if ($this->good === "FUEL") {
            // Its always going to be cheaper to buy at the buy end of a FUEL run.
            // navigate will save us if we don't have enough fuel to return.
            echo("Never fuel up at the selling end of a fuel run\n");
        } else {
            $t = $ship->fuel();
            if ($t) {
                $actualValue -= $t->getTotal();
            }
        }
        if ($amount > 0) {
            $transaction = $ship->sell([
                'symbol' => $this->good,
                'units' => $amount
            ]);
            $transaction->describe();
            $actualValue += $transaction->getTotal();
        }
        $this->sellPoint->updateTradeGoods();
        echo($ship->getId() . ": Trade run complete, made $$actualValue on $this->good\n");
    }

    public function getDescription() {
        $buy = $this->buyPoint->getWaypointSymbol();
        $sell = $this->sellPoint->getWaypointSymbol();
        $dis = number_format($this->getDistance(), 2);
        return "$this->good route $buy -> $sell $dis";
    }
}