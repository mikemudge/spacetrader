<?php

class Contract {
    private $id;
    private $deadline;
    private $payment;
    private $deliver;
    private $accepted;
    private $fulfilled;

    public function __construct(string $contractId) {
        $this->id = $contractId;
    }

    public static function create($data): Contract {
        $contract = new Contract($data['id']);
        $contract->deadline = $data['terms']['deadline'];
        $contract->payment = $data['terms']['payment'];
        $contract->deliver = $data['terms']['deliver'];
        $contract->accepted = $data['accepted'];
        $contract->fulfilled = $data['fulfilled'];
        return $contract;
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

    public function deliver(array $data) {
        $url = "https://api.spacetraders.io/v2/my/contracts/$this->id/deliver";
        $json_data = post_api($url, $data);
        return $json_data['data'];
    }

    public function fulfill() {
        $url = "https://api.spacetraders.io/v2/my/contracts/$this->id/fulfill";
        $json_data = post_api($url);
        return $json_data['data'];
    }
}