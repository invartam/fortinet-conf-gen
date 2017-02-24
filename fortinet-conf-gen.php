#!/usr/bin/php
<?php
require __DIR__ . '/vendor/autoload.php';

use App\Main;

Main::run($argv, count($argv) - 1);
