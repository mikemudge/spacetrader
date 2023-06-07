<?php
include_once "classes/autoload.php";
include_once "functions.php";

$ship = get_ship();
$ship->dock();
$ship->fuel();
