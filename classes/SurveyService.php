<?php

class SurveyService {
    const MINABLE = ["ICE_WATER","AMMONIA_ICE","QUARTZ_SAND","SILICON_CRYSTALS","IRON_ORE","COPPER_ORE","ALUMINUM_ORE","PRECIOUS_STONES"];

    /** @var array A map of location id to surveys */
    private array $locations;
    private Waypoint $asteroid;

    public function __construct(Agent $agent) {
        $this->locations = [];
        $this->asteroid = $agent->getAsteroids()[0];
    }

    public function perform(\Ship $ship, $good): bool {
        if (!$good) {
            // Without a good we are interested in, surveying isn't helpful?
            // TODO try to make more money (high value survey goods)?
            return false;
        }
        // TODO can we get this list from the asteroid?
        if (!in_array($good, SurveyService::MINABLE)) {
            return false;
        }

        // TODO surveying should just ensure we have something to use for the current contract?
        // May depend on how quickly the other ships are enabled to mine through a survey?
        if (!$ship->hasMount("MOUNT_SURVEYOR_I")) {
            return false;
        }
        $location = $this->asteroid->getId();
        // The ship needs to be at the asteroid to survey.
        if ($ship->getLocation() != $location) {
            return false;
        }

        $surveys = $this->getSurveys($location, $good);

        if (count($surveys) > 10) {
            echo($ship->getId() . ": Have enough surveys for $good at $location");
            // TODO need to cull this list to keep valuable surveys and remove others/expired one?
            return false;
        }

        // TODO should keep up the amount of each resource type in case we want that particular one?
        // Removing excess of other types is fine.
        // Consider size of each when counting how much we have?

        $ship->orbit();
        $surveys = $ship->survey();
        echo($ship->getId() . ": Surveyed at " . $ship->getLocation() . ", and found\n");
        foreach ($surveys as $s) {
            /** @var Survey $s */
            if ($s->hasResource($good)) {
                $this->locations[$location][$good][] = $s;
                echo($s->getDescription() . "\n");
            } else {
                echo("Ignoring " . $s->getDescription() . "\n");
            }
        }
        return true;
    }

    public function getSurvey(Ship $ship, $good): ?Survey {
        if (!$good) {
            return null;
        }
        // Lookup a survey for this location/resource.
        $location = $ship->getLocation();
        $surveys = $this->getSurveys($location, $good);

        $bestChance = 0;
        $bestSurvey = null;
        foreach($surveys as $survey) {
            /** @var Survey $survey */
            if ($survey->getLocation() == $ship->getLocation()) {
                $chance = $survey->getChance($good);
                if ($chance > $bestChance) {
                    $bestSurvey = $survey;
                }
            }
        }
        return $bestSurvey;
    }

    private function getSurveys(string $location, $good) {
        if (!isset($this->locations[$location])) {
            $this->locations[$location] = [];
        }
        if (!isset($this->locations[$location][$good])) {
            $this->locations[$location][$good] = [];
        }
        return $this->locations[$location][$good];
    }
}