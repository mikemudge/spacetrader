<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
// Preload all waypoints.
$agent->getSystemWaypoints();
$ships = $agent->getShips();
$asteroid = $agent->getAsteroids()[0];

// Need the command ship so that we can transfer to it.
// TODO could be the delivery ship, in case we replace it later with a fast/cargo ship.
$commandShip = null;
foreach($ships as $ship) {
    if ($ship->getRole() == "COMMAND") {
        $commandShip = $ship;
        break;
    }
}

$numShips = count($ships);
echo("Automating " . count($ships) . " ships\n");
$hoardGood = null;

// TODO we want actions/strategies.
// Assign ships to different controllers.
// Determine expected value of mining vs trade routes.
// Use the ships for appropriate tasks.
// Excavators should only mine.
// Command ship can deliver goods for contracts.
// Surveyor can keep markets up to date, and buy new ships.
while(true) {
    $goal = $agent->getFirstContract();
    if ($goal) {
        if ($hoardGood != $goal->getDeliver()[0]['tradeSymbol']) {
            // Change in hoard good.
            $hoardGood = $goal->getDeliver()[0]['tradeSymbol'];
            echo("Now hoarding " . $hoardGood . "\n");
        }
    }

    $ship = getNextAvailable($ships);
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
                // TODO we could/should use this ship to do something more useful.
                // Buy ships?
                // Negotiate/Accept new contracts?
                // Update markets which have stale data?
                // Do nothing with the surveyor, sleep for 100 seconds everytime and reload ships this often.
                $ship->setCooldown(100);
                //echo("SURVEYOR cooldown was reset to " .$ship->getCooldown() . "\n");
                // Reload does a full replacement of ships meaning the SURVEYOR has no cooldown.
//                $agent->buyMiningShip();
                $ships = $agent->reloadShips();
                break;
            case Ship::COMMAND:
                $howMuch = 0;
                if ($hoardGood) {
                    $howMuch = $ship->getCargo()->getAmountOf($hoardGood);
                }

                if ($howMuch > $ship->getCargo()->getCapacity() - 10) {
                    // Deliver good.
                    $ship->deliverContract($goal);
                } else {
                    // TODO see if there is some trade route we could use to benefit this trip?
                    // We can ignore fuel as we need to spend that already, just get the highest value cargo to bring?
                    $ship->navigateTo($asteroid->getId());
                    if ($ship->getCooldown() > 0) {
                        // If navigate did something, wait until arrival.
                        break;
                    }
                    $ship->extractAndSell($hoardGood);
                }
                break;
            case Ship::EXCAVATOR:
                // Should do nothing most of the time, as the ship should already be there.
                $ship->navigateTo($asteroid->getId());
                if ($ship->getCooldown() > 0) {
                    // If navigate did something, wait until arrival.
                    break;
                }

                // Transfer important goods to commandShip if possible.
                if ($hoardGood) {
                    $ship->transferAll($hoardGood, $commandShip);
                }

                $ship->extractAndSell($hoardGood);
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
function getNextAvailable(array $ships) {
    $best = -1;
    $bestShip = null;
    foreach ($ships as $ship) {
        $cooldown = $ship->getCooldown();
        if ($cooldown <= $best || $best === -1) {
            $best = $cooldown;
            $bestShip = $ship;
        }
    }
    return $bestShip;
}
