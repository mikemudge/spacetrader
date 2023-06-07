<?php

class Agent {
    /** @var string "MUDGE" for me */
    private $id;
    private $faction;
    private $headQuarters;
    private $credits;

    public function __construct() {
    }

    public function getInfo() {
        $json_data = get_api("https://api.spacetraders.io/v2/my/agent");

        $data = $json_data['data'];
        $this->id = $data['symbol'];
        // TODO does this change?
        $this->faction = $data['startingFaction'];
        $this->headQuarters = $data['headquarters'];
        $this->credits = $data['credits'];
        return $data;
    }

    /** @return Contract[] */
    public function getContracts(): array {
        $json_data = get_api("https://api.spacetraders.io/v2/my/contracts");

        $contractData = $json_data['data'];
        $contracts = [];
        foreach ($contractData as $data) {
            $contracts[] = Contract::create($data);
        }
        return $contracts;
    }

    public function printInfo() {
        $this->getInfo();
        echo("$this->id $this->faction \$" . number_format($this->credits) . " @ $this->headQuarters\n");
    }

    public function listContracts() {
        $contracts = $this->getContracts();
        foreach ($contracts as $contract) {
            $contractId = $contract->getId();
            $deliver = $contract->getDeliver();

            echo("Contract $contractId\n");
            foreach ($deliver as $item) {
                $tradeSymbol = $item['tradeSymbol'];
                $unitsRequired = $item['unitsRequired'];
                $unitsFulfilled = $item['unitsFulfilled'];
                echo("  $unitsFulfilled/$unitsRequired of $tradeSymbol\n");
            }
        }
    }

    public function getHq(): Waypoint {
        if (!$this->headQuarters) {
            $this->getInfo();
        }
        return new Waypoint($this->headQuarters);
    }
}