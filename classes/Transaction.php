<?php

class Transaction {

    private $type;
    private $units;
    private $tradeSymbol;
    private $totalPrice;
    private $pricePerUnit;

    public function __construct($data) {
        $this->type = $data['type'];
        $this->units = $data["units"];
        $this->tradeSymbol = $data["tradeSymbol"];
        $this->totalPrice = $data["totalPrice"];
        $this->pricePerUnit = $data['pricePerUnit'];
    }

    public function getDescription() {
        return "$this->type $this->units $this->tradeSymbol at $$this->pricePerUnit for $$this->totalPrice";
    }

    public function describe() {
        echo($this->getDescription() . "\n");
    }

    public function getTotal() {
        return $this->totalPrice;
    }
}