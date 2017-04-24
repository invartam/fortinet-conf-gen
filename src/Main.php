<?php
namespace Fortinet\ConfGenerator;

use Exception;
use Fortinet\ConfGenerator\Loader\ExcelLoader;
use Fortinet\Fortigate\Fortigate;
use Fortinet\Fortigate\Address;
use Fortinet\Fortigate\AddressGroup;
use phpseclib\Net\SSH2;

class Main {

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
