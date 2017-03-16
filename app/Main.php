<?php
namespace App;

use Exception;
use App\Loader\ExcelLoader;
use App\Loader\SchoolLoader;
use App\Model\Subnet;
use Fortinet\Fortigate\Fortigate;
use Fortinet\Fortigate\Address;
use Fortinet\Fortigate\AddressGroup;
use phpseclib\Net\SSH2;

class Main {

  private static $vrf = [];

  private static function unaccent($string)
  {
    return preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8'));
  }

  public static function run($argv, $argc)
  {
    if ($argc != 1) {
      print("Usage: $argv[0] <excel conf file>\n");
      exit;
    }
    $importedConf = new ExcelLoader($argv[1]);
    print $importedConf->getFortigate();
  }
}
