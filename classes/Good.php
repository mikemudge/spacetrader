<?php

class Good {
    private $id;
    private $sellPrice;
    private $buyPrice;
    private $tradeVolume;
    private $supply;

    public function __construct($data) {
        if (!isset($data['symbol'])) {
            display_json($data);
            throw new RuntimeException("Invalid data for good");
        }
        $this->id = $data['symbol'];
        $this->sellPrice = $data['sellPrice'];
        $this->buyPrice = $data['purchasePrice'];
        $this->tradeVolume = $data['tradeVolume'];
        $this->supply = $data['supply'];
    }

    public function getId() {
        return $this->id;
    }

    public function getSellPrice() {
        return $this->sellPrice;
    }

    public function getBuyPrice() {
        return $this->buyPrice;
    }

    public function saveData(): array {
        return [
            'symbol' => $this->id,
            'sellPrice' => $this->sellPrice,
            'purchasePrice' => $this->buyPrice,
            'supply' => $this->supply,
            'tradeVolume' => $this->tradeVolume
        ];
    }

}