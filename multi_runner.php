<?php
include_once "classes/autoload.php";
include_once "functions.php";

$agent = Agent::load();
// Preload all waypoints.
$agent->getSystemWaypoints();
$reloadShipsTime = time();
$asteroid = $agent->getAsteroids()[0];

$surveyService = $agent->getSurveyService();
$marketService = $agent->getMarketService();
$miningService = $agent->getMiningService();
$contractService = $agent->getContractService();
$financeService = $agent->getFinanceService();

// This chooses an appropriate ship for delivering contract goods.
$contractService->registerShip();

// TODO need a better way to reserve a ship for upgrades like additional mounts.
// TODO should keep track of ship income?

// TODO we want actions/strategies.
// Assign ships to different controllers.
// Determine expected value of mining vs trade routes.
// Use the ships for appropriate tasks.
// Excavators should only mine.
// Command ship can deliver goods for contracts.
// Surveyor can keep markets up to date, and buy new ships.
while(true) {
    $now = time();
    if ($now > $reloadShipsTime) {
        // Periodically every ~100 we run some checks.
        $agent->update();
        $reloadShipsTime = $now + 100;
    }

    $ship = getNextAvailable($agent->getShips());
    $shipId = $ship->getId();
    $role = $ship->getRole();
    $cooldown = $ship->getCooldown();

    if ($cooldown > 0) {
        if ($cooldown > 20) {
            // Only log for sleeps of over 20 seconds
            echo($ship->getId() . ": Waiting $cooldown to cooldown\n");
        }
        sleep($cooldown);
    }

    try {
        switch($role) {
            case Ship::SURVEYOR:
            case Ship::SATELLITE:
                // TODO contracts should normally be handled by the ship who delivers them?
                // however there is an initial accept which must be done, and this ship starts at HQ.
                if ($contractService->ensureContract($ship)) {
                    break;
                }

                // Purchase new ship?
                if ($financeService->purchaseShip($ship)) {
                    break;
                }

                // When there is nothing else for this ship to do it can circle markets and update their rates.
                if ($marketService->updateRates($ship)) {
                    break;
                }

                // TODO return to somewhere its likely to be needed next?
                // The ORBITAL_STATION for contracts, or the SHIPYARD for ship purchases?

                // Do nothing for 100 seconds so we aren't continually checking up on this ship.
//                echo($ship->getId() . ": Not needed, cooling down for 100\n");
                $ship->setCooldown(100);
                break;
            case Ship::COMMAND:
                // Check contract deliverables.
                if ($contractService->deliverContract($ship)) {
                    break;
                }
                // TODO see if there is some trade route we could use to benefit this trip?
                // We can ignore fuel as we need to spend that already, just get the highest value cargo to bring?
                $miningService->extractAndSell($ship);
                break;
            case Ship::HAULER:
                // Check contract deliverables.
                if ($contractService->deliverContract($ship)) {
                    break;
                }
                $ship->setCooldown(100);
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
                $miningService->extractAndSell($ship);
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
