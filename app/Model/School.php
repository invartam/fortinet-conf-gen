<?php
namespace App\Model;

use Exception;
use App\Model\Subnet;
use App\Model\Site;

class School extends Site {

  const OFFSET_SRV = array("SCCM" => 2,
                           "AD" => 3,
                           "FILES" => 4,
                           "HORUS" => 5
                         );

  public function generateServersIP()
  {
    if (!array_key_exists("VRF_Serveurs", $this->subnets)) {
      throw new Exception("No servers zone has been registered", 1);
    }
    $subnet = $this->subnets["VRF_Serveurs"];
    $ip = explode(".", $subnet->netip);
    foreach (self::OFFSET_SRV as $name => $offset) {
      $ip[3] = $ip[3] + $offset;
      $srvip = implode(".", $ip);
      $this->addSubnet(new Subnet("SRV_" . $name, $srvip, "255.255.255.255"));
    }
  }
}
