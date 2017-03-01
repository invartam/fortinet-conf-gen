<?php
namespace App\Model;

use Exception;
use App\Model\GetOverload;

class Subnet extends GetOverload {

  const TYPE_NET = 0;
  const TYPE_RANGE = 1;

  protected $name = "";
  protected $netip = "";
  protected $mask = "255.255.255.255";
  protected $type = self::TYPE_NET;

  public function __construct($name, $netip, $mask = "255.255.255.255", $range = false)
  {
    $this->name = $name;
    $this->netip = $netip;
    $this->mask = $mask;
    if ($range) {
      $this->type = self::TYPE_RANGE;
    }
  }
}
