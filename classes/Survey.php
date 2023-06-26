<?php

class Survey {

    public array $data;
    private string $size;
    private $expiry;
    private string $location;
    private array $deposits;
    private int $count;
    private array $chances;

    public function __construct($data) {
        $this->data = $data;
        $this->size = $data['size'];
        $this->expiry = $data['expiration'];
        $this->location = $data['symbol'];
        $this->deposits = [];
        foreach ($data['deposits'] as $d) {
            $this->deposits[] = $d['symbol'];
        }
        $this->count = count($this->deposits);
        // The number of times good appears divided by the total number of things we could get.
        $this->chances = array_count_values($this->deposits);
        foreach ($this->chances as $k=>$v) {
            $this->chances[$k] /= $this->count;
        }
    }

    public static function createMany($surveyData): array {
        $surveys = [];
        foreach ($surveyData as $data) {
            $surveys[] = new Survey($data);
        }
        return $surveys;
    }

    /**
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    public function getDescription() {
        return "$this->size deposit of " . join(",", $this->deposits) . ", expires $this->expiry";
    }

    public function hasResource($resource) {
        return in_array($resource, $this->deposits);
    }

    public function getLocation() {
        return $this->location;
    }

    public function getSize() {
        return $this->size;
    }

    public function getChance($good) {
        return $this->chances[$good];
    }

    public function getExpectedValue(Market $market) {
        $total = 0;
        foreach ($this->deposits as $ore) {
            $total += $market->getSellPrice($ore);
        }
        return $total / count($this->deposits);
    }
}