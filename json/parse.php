<?php
declare(strict_types=1);

use GarvinHicking\Gerrviewn\Core;

require __DIR__ . '/../vendor/autoload.php';

// @TODO: I want to be a Symfony Console script.
$app = new Core();
$app->cliParse();
