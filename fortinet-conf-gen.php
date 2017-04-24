#!/usr/bin/php
<?php
require __DIR__ . '/vendor/autoload.php';

use Fortinet\ConfGenerator\Main;

Main::run($argv, count($argv) - 1);
