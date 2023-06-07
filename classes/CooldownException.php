<?php

class CooldownException extends Exception {
    private $cooldown;

    public function __construct($message, $cooldown) {
        parent::__construct($message);
        $this->cooldown = $cooldown;
    }

    public function getCooldown() {
        return $this->cooldown;
    }
}