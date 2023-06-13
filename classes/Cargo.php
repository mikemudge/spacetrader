<?php

class Cargo {

    private int $units;
    private int $capacity;
    private array $inventory;

    public function __construct($data) {
        $this->capacity = $data['capacity'];
        $this->units = $data['units'];
        $this->inventory = $data['inventory'];
    }

    public function getSpace(): int {
        return $this->capacity - $this->units;
    }

    public function getInventory() {
        return $this->inventory;
    }

    public function getUnits() {
        return $this->units;
    }

    public function getCapacity() {
        return $this->capacity;
    }

    public function getDescription() {
        return $this->units . "/" . $this->capacity;
    }

    public function getAmountOf(string $good) {
        foreach ($this->inventory as $item) {
            if ($item['symbol'] == $good) {
                return $item['units'];
            }
        }
        // We don't have any of that good.
        return 0;
    }
}