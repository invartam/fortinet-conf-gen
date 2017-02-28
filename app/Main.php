<?php
namespace App;

use Exception;
use App\Loader\ExcelLoader;
use App\Loader\SchoolLoader;
use Fortinet\Fortigate\Fortigate;
use Fortinet\Fortigate\Address;
use Fortinet\Fortigate\AddressGroup;

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
    print $fgt;
  }

  public static function run($argv, $argc)
  {
    if ($argc != 2) {
      throw new Exception("You must give 2 arguments : name of the excel file containing base configuration and name of the excel file containing sites list", 1);
    }
    $importedConf = new ExcelLoader($argv[1]);
    // $vars = ["NET_PEDAGO" => "N-DMZ_MAINTENANCE"];
    // $importedConf->parsePolicyTemplate("college", $vars);
    $siteList = new SchoolLoader($argv[2]);
    self::registerSchoolSubnets($siteList, $importedConf->getFortigate());
    // print $importedConf->getFortigate();
  }
}
