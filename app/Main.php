<?php
namespace App;

use Exception;
use App\Loader\ExcelLoader;

class Main {

  public static function run($argv, $argc)
  {
    if ($argc != 1) {
      throw new Exception("You must give 1 argument : name of the excel file to load", 1);
    }
    $importedConf = new ExcelLoader($argv[1]);
  }
}
