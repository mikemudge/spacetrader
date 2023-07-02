<?php

class System {
    private array $data;
    private string $id;
    private string $type;
    private int $x;
    private int $y;
    private array $waypoints;

    public function __construct($data) {
        $this->data = $data;
        $this->id = $data['symbol'];
        $this->type = $data['type'];
        $this->x = $data['x'];
        $this->y = $data['y'];
        $this->waypoints = $data['waypoints'];
    }

    public function getData() {
        return $this->data;
    }

    public function getWaypoints() {
        return $this->waypoints;
    }

    public function getWaypointWithType(string $type) {
        foreach ($this->waypoints as $waypoint) {
            if ($waypoint['type'] == $type) {
                // TODO this waypoint data is missing some of the details to create a Waypoint.
                return $waypoint['symbol'];
            }
        }
        return null;
    }

    public function getId() {
        return $this->id;
    }
}