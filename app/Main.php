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

  private static function registerSchoolSubnets(SchoolLoader $schoolList, Fortigate $fgt)
  {
    foreach ($schoolList->getSchools() as $school) {
      foreach ($school->subnets as $name => $vrf) {
        $vrfname = strtoupper(str_replace(" ", "_", self::unaccent($name)));
        $gaddrname = "GN-" . $school->name . "-$vrfname";
        self::$vrf[$vrfname][] = $gaddrname;
        $gaddr = new AddressGroup($gaddrname);
        foreach ($vrf as $subnet) {
          $addrname = "N-" . $school->name . "-" . strtoupper(str_replace(" ", "_", self::unaccent($name) . "-" . self::unaccent($subnet->name)));
          $addr = new Address($addrname, $subnet->netip, $subnet->mask);
          $gaddr->addAddress($addr);
          $fgt->addAddress($addr);
        }
        $fgt->addAddressGroup($gaddr);
      }
    }
    foreach (self::$vrf as $name => $subnets) {
      $addrgrp = new AddressGroup("GN-$name");
      foreach ($subnets as $subnet) {
        $addrgrp->addAddress($fgt->addressGroups[$subnet]);
      }
      $fgt->addAddressGroup($addrgrp);
    }
  }

  private static function registerSchoolServers(SchoolLoader $schoolList, Fortigate $fgt)
  {
    foreach ($schoolList->getServers() as $type => $servers) {
      $grpname = "GN-COL-$type";
      if (!array_key_exists($grpname, $fgt->addressGroups)) {
        $fgt->addAddressGroup(new AddressGroup($grpname));
      }
      foreach ($servers as $name => $server) {
        if (array_key_exists($name, $fgt->addresses)) {
          throw new Exception("Server $name is already registered", 1);
        }
        $address = new Address($name, $server->netip, $server->mask, $server->type == Subnet::TYPE_RANGE);
        $fgt->addAddress($address);
        $fgt->addressGroups[$grpname]->addAddress($address);
      }
    }
  }

  private static function loadVars($file)
  {
    $vars = [];
    $lines = file($file);
    foreach ($lines as $line) {
      $var = explode(" = ", $line);
      $vars[trim($var[0])] = trim($var[1]);
    }
    return $vars;
  }

  public static function run($argv, $argc)
  {
    if ($argc != 3) {
      print("Usage: $argv[0] <excel conf file> <site list file> <vars file>");
      exit;
    }
    // print "[INFO] Parsing global configuration excel file\n";
    $importedConf = new ExcelLoader($argv[1]);
    // print "[INFO] Parsing school list excel file\n";
    $siteList = new SchoolLoader($argv[2]);
    // print "[INFO] Parsing variables file\n";
    $vars = self::loadVars($argv[3]);
    // print "[INFO] Generating school subnets\n";
    self::registerSchoolSubnets($siteList, $importedConf->getFortigate());
    // print "[INFO] Generating schools servers IP\n";
    self::registerSchoolServers($siteList, $importedConf->getFortigate());
    // print "[INFO] Parsing policy templates\n";
    $importedConf->parsePolicyTemplate("college", $vars, "FLUX COLLEGES");
    print $importedConf->getFortigate();
  }
}
