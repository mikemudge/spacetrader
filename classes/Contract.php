<?php

class Contract {
    private $id;
    private $deadline;
    private $payment;
    private $deliver;
    private $accepted;
    private $fulfilled;

    private function __construct(string $contractId) {
        $this->id = $contractId;
    }

    public static function create($data): Contract {
        $contract = new Contract($data['id']);
        $contract->updateFromData($data);
        return $contract;
    }

    private function updateFromData($data) {
        $this->deadline = $data['terms']['deadline'];
        $this->payment = $data['terms']['payment'];
        $this->deliver = $data['terms']['deliver'];
        $this->accepted = $data['accepted'];
        $this->fulfilled = $data['fulfilled'];
    }

    public function getId(): string {
        return $this->id;
    }

    public function getDeadline() {
        return $this->deadline;
    }

    public function getPayment() {
        return $this->payment;
    }

    public function getDeliver() {
        return $this->deliver;
    }

    public function getAccepted() {
        return $this->accepted;
    }

    public function getFulfilled() {
        return $this->fulfilled;
    }

    public function deliverGoods(Ship $ship) {
        $amount = $ship->getCargo()->getAmountOf($this->getGood());
        if ($amount == 0) {
            echo($ship->getId() . ": has 0 " . $this->getGood() . ", can't deliver anything\n");
            exit;
        }

        // Go to the location and deliver.
        $ship->completeNavigateTo($this->getLocation());
        $ship->deliverContract($this);
    }

    public function deliver(array $data) {
        $url = "https://api.spacetraders.io/v2/my/contracts/$this->id/deliver";
        $json_data = post_api($url, $data);
        $this->updateFromData($json_data['data']['contract']);
        // Return this because the ship cargo is also in it.
        return $json_data['data'];
    }

    public function fulfill() {
        $url = "https://api.spacetraders.io/v2/my/contracts/$this->id/fulfill";
        $json_data = post_api($url);
        $this->updateFromData($json_data['data']['contract']);
        Agent::get()->updateFromData($json_data['data']['agent']);

        $acceptFee = $this->getPayment()['onFulfilled'];
        echo("Got $acceptFee for accepting a contract\n");
    }

    public function accept() {
        $url = "https://api.spacetraders.io/v2/my/contracts/$this->id/accept";
        $json_data = post_api($url);
        $this->updateFromData($json_data['data']['contract']);

        $acceptFee = $this->getPayment()['onAccepted'];
        echo("Got $acceptFee for accepting a contract\n");
        return $json_data['data'];
    }

    public function getDescription() {
        $result = "";
        foreach ($this->deliver as $item) {
            $tradeSymbol = $item['tradeSymbol'];
            $unitsRequired = $item['unitsRequired'];
            $unitsFulfilled = $item['unitsFulfilled'];
            $result .= "  $unitsFulfilled/$unitsRequired of $tradeSymbol";
        }
        return $result;
    }

    public function describe() {
        $payment = $this->getPayment();
        $price = number_format($payment['onFulfilled']);
        echo("Current contract: $this->id ($$price)\n");
        echo($this->getDescription() . "\n");
    }

    public function getRemaining() {
        // TODO assumes a single deliver.
        return $this->deliver[0]['unitsRequired'] - $this->deliver[0]['unitsFulfilled'];
    }

    public function getGood() {
        // TODO assumes a single deliver.
        return $this->deliver[0]['tradeSymbol'];
    }

    public function getLocation() {
        // TODO assumes a single deliver.
        return $this->deliver[0]['destinationSymbol'];
    }
}