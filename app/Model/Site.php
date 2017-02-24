<?php
namespace App\Model;

use Exception;
use App\Model\Subnet;

class Site extends GetOverload {

  protected $name = "";
  protected $subnets = [];

  public function addSubnet(Subnet $subnet)
  {
    if (!array_key_exists($subnet->name, $this->subnets)) {
      $this->subnets[$subnet->name] = $subnet;
    }
    throw new Exception("The subnet $subnet->name is already registered", 1);
  }
}
