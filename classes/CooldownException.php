<?php

class CooldownException extends Exception {
    private int $cooldown;

    public function __construct($message, int $cooldown) {
        parent::__construct($message);
        $this->cooldown = $cooldown;
    }

    public function getCooldown(): int {
        return $this->cooldown;
    }
}