<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();

$url = "https://api.spacetraders.io/v2/my/ships?limit=20";
$json_data = get_api($url);

$ships = $json_data['data'];
if (has_arg("--describe")) {
    display_json($ships);
}

echo("cost\tname\t\tspeed\tpower\tmodules\tmounts\tfuel\tcargo\tcrew\tdrill\textra\n");
foreach ($ships as $i => $ship) {
    // This only exists for ships which can be brought.
    $dollars = "$" . number_format($ship['purchasePrice'] ?? 0);
    $type = $ship['type'] ?? $ship['registration']['role'];
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

    $description = "$speed\t$powerUsed/$power\t$modulesUsed/$modules\t$mountsUsed/$mounts\t$fuel\t$cargo\t$crewUsed/$crew\t$mineStrength";
    if ($extraModules) {
        $description .= "\t" . json_encode($extraModules);
    }
    $t1 = tabs_for($dollars, 1);
    $t2 = tabs_for($type, 2);
    echo($dollars . $t1 . $type . $t2 . $description .  "\n");
}
