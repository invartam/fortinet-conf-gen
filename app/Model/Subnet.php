<?php
namespace App\Model;

use Exception;
use App\Model\GetOverload;

class Subnet extends GetOverload {

  const TYPE_NET = 0
  const TYPE_HOST = 1;

  private $name = "";
  private $netip = "";
  private $mask = "";
  private $type = self::TYPE_HOST;

  public function __construct($name, $netip, $mask)
  {
    $this->name = $name;
    $this->netip = $netip;
    $this->mask = $mask;
    if (($mask == "255.255.255.255") || ($mask == 32)){
      $this->type = self::TYPE_HOST;
    }
  }
}
