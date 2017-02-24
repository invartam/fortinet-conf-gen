<?php
namespace App;

use Exception;
use App\Model\CD37;
use App\Model\School;
use App\Loader\ExcelLoader;
use Fortinet\Fortigate\Fortigate;
use Fortinet\Fortigate\Address;
use Fortinet\Fortigate\AddressGroup;
use Fortinet\Fortigate\Service;
use Fortinet\Fortigate\ServiceGroup;
use Fortinet\Fortigate\NetDevice;
use Fortinet\Fortigate\Zone;
use Fortinet\Policy\Policy;

class Main {

  public static function run($argv, $argc)
  {
    if ($argc != 1) {
      throw new Exception("You must give 1 argument : name of the excel file to load", 1);
    }
    $importedConf = new ExcelLoader($argv[1]);
  }
}
