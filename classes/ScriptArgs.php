<?php

class ScriptArgs {

    public function __construct() {
    }

    public function getOptionalShip(): ?Ship {
        $shipSymbol = get_arg("--ship");
        if ($shipSymbol) {
            return Ship::load($shipSymbol);
        }
        return null;
    }
}