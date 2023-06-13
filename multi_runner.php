<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
// Preload all waypoints.
$agent->getSystemWaypoints();
$ships = $agent->getShips();
$reloadShipsTime = time() + 100;
$asteroid = $agent->getAsteroids()[0];

$noCommand = has_arg("--no_command");

// Need the command ship so that we can transfer to it.
// TODO could be the delivery ship, in case we replace it later with a fast/cargo ship.
$commandShip = null;
foreach($ships as $ship) {
    if ($ship->getRole() == "COMMAND") {
        $commandShip = $ship;
        break;
    }
}

// TODO need a better way to reserve a ship for upgrades like additional mounts.
$excludedShips = [];
// TODO should keep track of ship income?
$numShips = count($ships);
echo("Automating " . count($ships) . " ships\n");
$goal = null;

// TODO we want actions/strategies.
// Assign ships to different controllers.
// Determine expected value of mining vs trade routes.
// Use the ships for appropriate tasks.
// Excavators should only mine.
// Command ship can deliver goods for contracts.
// Surveyor can keep markets up to date, and buy new ships.
while(true) {
    $c = $agent->getUnfulfilledContract();
    if ($goal !== $c) {
        $goal = $c;
        if ($goal) {
            echo("Contract changed\n");
            $goal->describe();
        } else {
            echo("Contract ended\n");
        }
    }
    if ($goal) {
        $contractGood = $goal->getGood();
    }

    $now = time();
    if ($now > $reloadShipsTime) {
        $ships = $agent->reloadShips();
        $reloadShipsTime = $now + 100;
    }

    $ship = getNextAvailable($ships, $excludedShips);
    $shipId = $ship->getId();
    $role = $ship->getRole();
    $cooldown = $ship->getCooldown();

    if ($cooldown > 0) {
        if ($cooldown > 10) {
            // Only log for sleeps of over 10 seconds
            echo($ship->getId() . ": Waiting $cooldown to cooldown\n");
        }
        sleep($cooldown);
    }

    try {
        switch($role) {
            case Ship::SURVEYOR:
                // Do nothing with the surveyor, sleep for 100 seconds to avoid it showing up.
                $ship->setCooldown(100);

                // TODO we could/should use this ship to do something more useful.
                // Buy ships? Negotiate/Accept new contracts? Update markets which have stale data?
//                $agent->buyMiningShip();
                break;
            case Ship::COMMAND:
                if ($noCommand) {
                    break;
                }
                $howMuch = 0;
                if ($contractGood) {
                    $howMuch = $ship->getCargo()->getAmountOf($contractGood);
                }

                if ($howMuch > $ship->getCargo()->getCapacity() - 10) {
                    // Deliver good.
                    $howMuch = min($goal->getRemaining(), $ship->getCargo()->getAmountOf($contractGood));
                    if ($ship->getLocation() != $goal->getLocation()) {
                        echo($ship->getId() . ": Need to travel to deliver $howMuch $contractGood for contract\n");
                        $ship->navigateTo($goal->getLocation());
                        break;
                    }
                    $ship->deliverContract($goal);
                } else {
                    // TODO see if there is some trade route we could use to benefit this trip?
                    // We can ignore fuel as we need to spend that already, just get the highest value cargo to bring?
                    $ship->navigateTo($asteroid->getId());
                    if ($ship->getCooldown() > 0) {
                        // If navigate did something, wait until arrival.
                        break;
                    }
                    $ship->extractAndSell($contractGood);
                }
                break;
            case Ship::REFINERY:
                // TODO handle what this ship does?
                // If we have enough resource refine, otherwise collect from miners around it?
                // Can't mine.
                /*
                    {
                        "error": {
                            "message": "Ship MUDGE-C does not have a required mining laser mount.",
                            "code": 4243,
                            "data": {
                                "shipSymbol": "MUDGE-C",
                                "miningLasers": [
                                    "MOUNT_MINING_LASER_I",
                                    "MOUNT_MINING_LASER_II",
                                    "MOUNT_MINING_LASER_III"
                                ]
                            }
                        }
                    }
                 */
                $ship->setCooldown(1000);
                break;
            case Ship::EXCAVATOR:
                // Should do nothing most of the time, as the ship should already be there.
                $ship->navigateTo($asteroid->getId());
                if ($ship->getCooldown() > 0) {
                    // If navigate did something, wait until arrival.
                    break;
                }

                // Transfer important goods to commandShip if possible.
                // TODO should we do this at sell time only to reduce API calls?
                if ($contractGood) {
                    $ship->transferAll($contractGood, $commandShip);
                }

                $ship->extractAndSell($contractGood);
                break;
            default:
                throw new RuntimeException("Unknown ship role $role");
        }
    } catch (CooldownException $e) {
        // We can skip the ship for now, it will have updated its cooldown, and should not be selected again for a while.
        $cooldown = $ship->getCooldown();
        echo("$shipId was not ready, cooldown updated to $cooldown\n");
    }
}

/** @var Ship[] $ships */
function getNextAvailable(array $ships, array $excludedShips) {
    $best = -1;
    $bestShip = null;
    foreach ($ships as $ship) {
        if (in_array($ship->getId(), $excludedShips)) {
            // Don't use this ship.
            continue;
        }
        $cooldown = $ship->getCooldown();
        if ($cooldown <= $best || $best === -1) {
            $best = $cooldown;
            $bestShip = $ship;
        }
    }
    return $bestShip;
}
