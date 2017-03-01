<?php
namespace App\Model;

use Exception;
use App\Model\Subnet;
use App\Model\GetOverload;

class School extends GetOverload {

  protected $name = "";
  protected $subnets = [];

  public function __construct($name)
  {
    $this->name = $name;
  }

  public function addSubnet($vrf, Subnet $subnet)
  {
    if (array_key_exists($vrf, $this->subnets) && array_key_exists($subnet->name, $this->subnets[$vrf])) {
      throw new Exception("The subnet $subnet->name is already registered", 1);
    }
    $this->subnets[$vrf][$subnet->name] = $subnet;
  }

  private function calculateIP($vrf, $subnet, $hostOffset)
  {
    if (!array_key_exists($vrf, $this->subnets) && !array_key_exists($subnet, $this->subnet[$vrf])){
      throw new Exception("VRF $vrf or subnet $subnet does not exists in school $this->name", 1);
    }
    $net = $this->subnets[$vrf][$subnet]->netip;
    $chunks = explode(".", $net);
    $chunks[3] += $hostOffset;
    return(implode(".", $chunks));
  }
}
