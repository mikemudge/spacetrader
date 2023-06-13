<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
$shipyards = $agent->getSystemShipyards();
$shipyard = $shipyards[0]->getShipyard();
// TODO Should cache these numbers like we do with market tradeGoods?

$ship = null;
$shipSymbol = get_arg("--ship");
if ($shipSymbol) {
    $ship = Ship::load($shipSymbol);
}

if ($ship && $ship->hasArrivedAt($shipyards[0])) {
    // Ship is on location
    $ships = $shipyard['ships'];
    if (has_arg("--describe")) {
        display_json($ships);
    }

    echo("cost\t\tname\t\t\tspeed\tpower\tmodules\tmounts\tfuel\tcargo\tcrew\tdrill\textra\n");
    foreach ($ships as $i => $ship) {
        $dollars = "$" . number_format($ship['purchasePrice']);
        $t1 = tabs_for($dollars, 2);
        $t2 = tabs_for($ship['type'], 3);
        $speed = $ship['engine']['speed'];
        $power = $ship['reactor']['powerOutput'];
        $modules = $ship['frame']['moduleSlots'];
        $mounts = $ship['frame']['mountingPoints'];
        $fuel = $ship['frame']['fuelCapacity'];

        $powerUsed = $ship['engine']['requirements']['power'] ?? 0;
        $powerUsed += $ship['frame']['requirements']['power'] ?? 0;
        // reactor provides power, doesn't require it?
        $crewUsed = $ship['reactor']['requirements']['crew'] ?? 0;
        $crewUsed += $ship['engine']['requirements']['crew'] ?? 0;
        $crewUsed += $ship['frame']['requirements']['crew'] ?? 0;

        $modulesUsed = 0;
        $cargo = 0;
        $crew = 0;
        $extraModules = [];
        foreach($ship['modules'] as $m) {
            if (substr($m['symbol'],0, 20) == "MODULE_CREW_QUARTERS") {
                $crew += $m['capacity'];
            } else if (substr($m['symbol'],0, 17) == "MODULE_CARGO_HOLD") {
                $cargo += $m['capacity'];
            } else {
                $extraModules[] = $m['symbol'];
            }

            $powerUsed += $m['requirements']['power'] ?? 0;
            $crewUsed += $m['requirements']['crew'] ?? 0;
            $modulesUsed += $m['requirements']['slots'] ?? 0;
        }

        $mountsUsed = count($ship['mounts']);
        $mineStrength = 0;
        foreach($ship['mounts'] as $m) {
            if (substr($m['symbol'],0, 18)  == 'MOUNT_MINING_LASER') {
                $mineStrength += $m['strength'];
            } else {
                $extraModules[] = $m['symbol'];
            }

            // Only requires power and crew?
            $powerUsed += $m['requirements']['power'] ?? 0;
            $crewUsed += $m['requirements']['crew'] ?? 0;
        }

        // TODO is a MODULE_MINERAL_PROCESSOR module is required for a MINING_LASER?
        // MOUNT_MINING_LASER_II requires 2 crew + 2 power.
        // MOUNT_MINING_LASER_I requires only 2 power.


        // MOUNT_TURRET_I and MOUNT_MISSILE_LAUNCHER_I don't have stats yet?

        /*
         MODULE_ORE_REFINERY_I supports the creation of smelted goods like
         "production": [
            "IRON",
            "COPPER",
            "SILVER",
            "GOLD",
            "ALUMINUM",
            "PLATINUM",
            "URANITE",
            "MERITIUM"
        ],
         */

        // TODO does adding an extra MOUNT_MINING_LASER_I make mining faster?
        // TODO does adding an extra MODULE_MINERAL_PROCESSOR_I help mine faster?

        $description = "$speed\t$powerUsed/$power\t$modulesUsed/$modules\t$mountsUsed/$mounts\t$fuel\t$cargo\t$crewUsed/$crew\t$mineStrength";
        if ($extraModules) {
            $description .= "\t" . json_encode($extraModules);
        }
        echo($dollars . $t1 . $ship['type'] . $t2 . $description .  "\n");
    }
} else {
    $ships = $shipyard['shipTypes'];

    echo("Only ship types are available without a ship at the location ". $shipyards[0]->getId() . "\n");
    foreach ($ships as $i => $ship) {
        echo($ship['type'] .  "\n");
    }
}

/*
cost		name			        speed	power	modules	mounts	fuel	cargo	crew	drill
$69,685		SHIP_PROBE		        2	    2/3	    0/0	    0/0	    0	    0	    0/0	    0
$377,115	SHIP_ORE_HOUND		    10	    15/31	5/5	    2/3	    900	    60	    32/40	25
$341,703	SHIP_LIGHT_HAULER	    10	    15/15	6/6	    1/1	    1700	120	    52/80	0
$1,731,660	SHIP_REFINING_FREIGHTER	30	    39/40	12/12	3/3	    2300	120	    152/160	0
$86,518		SHIP_MINING_DRONE	    2	    5/15	3/3	    1/2	    100	    30	    0/0	    10
*/

// It might be worth getting an SHIP_ORE_HOUND earlier next reset?
// SHIP_MINING_DRONE's are cheap and do ok, but higher drill and more cargo would be worth the cost?
// Have to see if its faster or mines more?
// Would travel faster by 5x, and use fuel better.
// Also has room to add on a 2nd MOUNT_MINING_LASER_II (if multiple are allowed).

// TODO need a way to find my current ships mounts/modules space etc.
// MUDGE-A with 2 tier 1 drills (takes longer, does it fill up faster?)
// Mining 9 ICE_WATER takes 79
// MUDGE-A: Mining 12 ICE_WATER takes 79
// MUDGE-A: Mining 8 QUARTZ_SAND takes 79
// MUDGE-A: Mining 7 ICE_WATER takes 79

// MUDGE-B (SHIP_ORE_HOUND) with 1 tier 2 drill
// Mining 14 SILICON_CRYSTALS takes 79
// Mining 12 PRECIOUS_STONES takes 79
// Mining 15 AMMONIA_ICE takes 79
// So drill means more ore mined, but also slightly more cooldown.
// does it mine better ores? Check avg $/m over a time?
// Some contracts require specific ores as well?
// TODO test survey abilities?

// Investigate MOUNT_SURVEYOR_I usage which SHIP_ORE_HOUND and SHIP_LIGHT_HAULER both have.
