<?php
namespace App\Model;

use Exception;
use App\Model\Subnet;

class Site extends GetOverload {

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
}
