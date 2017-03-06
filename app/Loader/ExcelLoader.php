<?php
namespace App\Loader;

use Exception;
use Fortinet\Fortigate\Fortigate;
use Fortinet\Fortigate\NetDevice;
use Fortinet\Fortigate\VPN;
use Fortinet\Fortigate\Address;
use Fortinet\Fortigate\AddressGroup;
use Fortinet\Fortigate\Service;
use Fortinet\Fortigate\ServiceGroup;
use Fortinet\Fortigate\Zone;
use Fortinet\Fortigate\Policy\Policy;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelLoader {

  const TAB_INFOS = "Suivi";
  const TAB_INTERFACES = "Interfaces";
  const TAB_ADDRESS = "Adresses";
  const TAB_ADDRESSGROUP = "AdressesGroup";
  const TAB_SERVICE = "Services";
  const TAB_SERVICEGROUP = "ServicesGroup";
  const TAB_POLICY = "Policies";
  const TAB_VPN = "VPN IPSec";

  private $source;
  private $fortigate;
  private $policySection;

  public function __construct($file)
  {
    if (!file_exists($file)) {
      throw new Exception("The file $file does not exist", 1);
    }
    $this->source = IOFactory::load($file);
    $this->fortigate = new Fortigate();

    // $this->getInfos();
    $this->parseInterfaces();
    $this->parseVPN();
    $this->parseAddress();
    $this->parseAddressGroup();
    $this->parseService();
    $this->parseServiceGroup();
    $this->parsePolicy();
  }

  private function getInfos()
  {
    $sheet = $this->source->getSheetByName(self::TAB_INFOS);
    $author = $sheet->getCell("E2")->getValue();
    $updateTime = $sheet->getCell("E4")->getFormattedValue();
    $version = $sheet->getCell("E5")->getValue();

    print "Author: " . $author . "\n";
    print "Last modified: " . $updateTime . "\n";
    print "Version: " . $version . "\n";
  }

  private function parseInterfaces()
  {
    $sheet = $this->source->getSheetByName(self::TAB_INTERFACES);
    foreach ($sheet->getRowIterator(3) as $row) {
      $name = trim($row->getCellIterator()->seek("B")->current()->getValue());
      if (empty($name)) {
        break;
      }
      $type = trim($row->getCellIterator()->seek("C")->current()->getValue());
      $vlanid = trim($row->getCellIterator()->seek("D")->current()->getValue());
      $devices = trim($row->getCellIterator()->seek("E")->current()->getValue());
      $aDevices = [];
      if (!empty($devices)) {
        $aDevices = explode(" ", $devices);
        if ($type == "VLAN" && count($aDevices) != 1) {
          throw new Exception("Interface $name is of type VLAN, there must be one and only one physical interface set", 1);
        }
        foreach ($aDevices as $device) {
          if (!array_key_exists($device, $this->fortigate->interfaces)) {
            $this->fortigate->addNetDevice(new NetDevice($device));
          }
        }
      }
      $ip = trim($row->getCellIterator()->seek("F")->current()->getValue());
      $mask = trim($row->getCellIterator()->seek("G")->current()->getValue());
      $alias = trim($row->getCellIterator()->seek("H")->current()->getValue());
      $vdom = trim($row->getCellIterator()->seek("I")->current()->getValue());
      $zone = trim($row->getCellIterator()->seek("J")->current()->getValue());
      if (!empty($zone) && !array_key_exists($zone, $this->fortigate->zones)) {
        $newZone = new Zone();
        $newZone->setName($zone);
        $this->fortigate->addZone($newZone);
      }
      if (array_key_exists($name, $this->fortigate->interfaces)) {
        throw new Exception("Duplicate interface $name at row " . $row->getRowIndex(), 1);
      }
      $if = new NetDevice($name);
      if ($type == "VLAN") {
        $if->setType(NetDevice::VLAN);
        $if->setVlanID($vlanid);
        $if->setVlanDevice($this->fortigate->interfaces[$devices]);
      }
      if ($type == "LAGG") {
        $if->setType(NetDevice::LAGG);
        foreach ($aDevices as $device) {
          $if->addLaggNetDevice($this->fortigate->interfaces[$device]);
        }
      }
      if (!empty($ip)) {
        $if->setIP($ip);
        if (empty($mask)) {
          throw new Exception("A mask must be set for interface $name at row " . $row->getRowIndex(), 1);
        }
        $if->setMask($mask);
      }
      if (!empty($alias)) {
        $if->setAlias($alias);
      }
      if (!empty($zone)) {
        $this->fortigate->zones[$zone]->addInterface($if);
      }
      $this->fortigate->addNetDevice($if);
    }
  }

  private function parseVPN()
  {
    $sheet = $this->source->getSheetByName(self::TAB_VPN);
    foreach ($sheet->getRowIterator(3) as $row) {
      $name = trim($row->getCellIterator()->seek("B")->current()->getValue());
      if (empty($name)) {
        break;
      }
      if (array_key_exists($name, $this->fortigate->VPNs)) {
        throw new Exception("Duplicate VPN $name at row " . $row->getRowIndex(), 1);
      }
      $this->fortigate->addVPN(new VPN($name));
    }
  }

  private function parseAddress()
  {
    $sheet = $this->source->getSheetByName(self::TAB_ADDRESS);
    foreach ($sheet->getRowIterator(3) as $row) {
      $name = trim($row->getCellIterator()->seek("B")->current()->getValue());
      $ip = trim($row->getCellIterator()->seek("C")->current()->getValue());
      $mask = trim($row->getCellIterator()->seek("D")->current()->getValue());
      if (empty($name) || empty($ip) || empty($mask)) {
        break;
      }
      if (array_key_exists($name, $this->fortigate->addresses)) {
        throw new Exception("Duplicate address $name at row " . $row->getRowIndex(), 1);
      }
      $this->fortigate->addAddress(new Address($name, $ip, $mask));
    }
  }

  private function parseAddressGroup()
  {
    $sheet = $this->source->getSheetByName(self::TAB_ADDRESSGROUP);
    foreach ($sheet->getRowIterator(3) as $row) {
      $name = trim($row->getCellIterator()->seek("B")->current()->getValue());
      $member = trim($row->getCellIterator()->seek("C")->current()->getValue());
      if (empty($name) || empty($member)) {
        break;
      }
      if (!array_key_exists($member, $this->fortigate->addresses) && !array_key_exists($member, $this->fortigate->addressGroups)) {
        throw new Exception("Cannot add non existent address or address group $member in address group $name at row " . $row->getRowIndex(), 1);
      }
      if (!array_key_exists($name, $this->fortigate->addressGroups)) {
        $this->fortigate->addAddressGroup(new AddressGroup($name));
      }
      $this->fortigate->addressGroups[$name]->addAddress($this->fortigate->addresses[$member]);
    }
  }

  private function parseService()
  {
    $sheet = $this->source->getSheetByName(self::TAB_SERVICE);
    foreach ($sheet->getRowIterator(3) as $row) {
      $name = trim($row->getCellIterator()->seek("B")->current()->getValue());
      $portl = trim($row->getCellIterator()->seek("C")->current()->getValue());
      $porth = trim($row->getCellIterator()->seek("D")->current()->getValue());
      $proto = trim($row->getCellIterator()->seek("E")->current()->getValue());
      if (empty($name) || empty($proto)) {
        break;
      }
      $l4proto = "";
      $l3proto = Service::PROTO_IP;;
      if ($proto == "UDP") {
        $l4proto = Service::L4_UDP;
      }
      if ($proto == "TCP") {
        $l4proto = Service::L4_TCP;
      }
      if ($proto == "ICMP") {
        $l3proto = Service::PROTO_ICMP;
      }
      if ($l3proto != Service::PROTO_ICMP && (empty($l3proto) || empty($l4proto))) {
        throw new Exception("Non existent protocol $proto at row " . $row->getRowIndex(), 1);
      }
      if (array_key_exists($name, $this->fortigate->services)) {
        throw new Exception("Duplicate service $name at row " . $row->getRowIndex(), 1);
      }
      $portrange = "";
      if ($l3proto != Service::PROTO_ICMP) {
        $portrange = empty($porth) ? $portl : "$portl-$porth";
      }
      $this->fortigate->addService(new Service($name, $l3proto, $l4proto, $portrange));
    }
  }

  private function parseServiceGroup()
  {
    $sheet = $this->source->getSheetByName(self::TAB_SERVICEGROUP);
    foreach ($sheet->getRowIterator(3) as $row) {
      $name = trim($row->getCellIterator()->seek("B")->current()->getValue());
      $member = trim($row->getCellIterator()->seek("C")->current()->getValue());
      if (empty($name) || empty($member)) {
        break;
      }
      if (!array_key_exists($member, $this->fortigate->services) && !array_key_exists($member, $this->fortigate->serviceGroups)) {
        throw new Exception("Cannot add non existent service or service group $member in service group $name at row " . $row->getRowIndex(), 1);
      }
      if (!array_key_exists($name, $this->fortigate->serviceGroups)) {
        $this->fortigate->addServiceGroup(new ServiceGroup($name));
      }
      if (array_key_exists($member, $this->fortigate->services)) {
        $this->fortigate->serviceGroups[$name]->addService($this->fortigate->services[$member]);
      }
      if (array_key_exists($member, $this->fortigate->serviceGroups)) {
        $this->fortigate->serviceGroups[$name]->addService($this->fortigate->serviceGroups[$member]);
      }
    }
  }

  public function parsePolicyTemplate($tplName, $vars, $section = "")
  {
    if (!array_key_exists($tplName, $this->templates)) {
      throw new Exception("Policy template $tplName does not exists", 1);
    }
    if (!empty($section)) {
      $this->policySection = $section;
    }
    foreach ($this->templates[$tplName] as $row) {
      $this->addPolicy($row, $vars);
    }
  }

  private function parseVar($str, $vars)
  {
    if (empty($vars)) {
      return $str;
    }
    $result = [];
    if (preg_match_all("/{(\w+)}/" , $str, $result)) {
      $str = "";
      foreach ($result[1] as $value) {
        if (!array_key_exists($value, $vars)) {
          throw new Exception("The variable $value is not defined", 1);
        }
        $str .= $vars[$value] . " ";
      }
    }
    return $str;
  }

  private function addPolicy($row, $vars = [])
  {
    $ignore = trim($row->getCellIterator()->seek("B")->current()->getValue());
    $sourceZone = trim($this->parseVar($row->getCellIterator()->seek("C")->current()->getValue(), $vars));
    $destinationZone = trim($this->parseVar($row->getCellIterator()->seek("D")->current()->getValue(), $vars));
    $sourceAddress = trim($this->parseVar($row->getCellIterator()->seek("E")->current()->getValue(), $vars));
    $destinationAddress = trim($this->parseVar($row->getCellIterator()->seek("F")->current()->getValue(), $vars));
    $service = trim($this->parseVar($row->getCellIterator()->seek("G")->current()->getValue(), $vars));
    $action = trim($row->getCellIterator()->seek("H")->current()->getValue());
    $log = trim($row->getCellIterator()->seek("I")->current()->getValue());
    $user = trim($row->getCellIterator()->seek("J")->current()->getValue());
    $nat = trim($row->getCellIterator()->seek("K")->current()->getValue());
    $desc = trim($row->getCellIterator()->seek("L")->current()->getValue());
    if (!is_numeric($ignore) && empty($sourceZone)) {
      $this->policySection = $ignore;
      return;
    }
    if (!empty($ignore)) {
      return;
    }
    if (preg_match("/Template/", $desc) && empty($vars)) {
      $tplName = explode(" ", $desc)[1];
      $this->templates[$tplName][] = $row;
      return;
    }
    $policy = new Policy();
    foreach (explode(" ", $sourceZone) as $zone) {
      if ($zone == "all") {
        $policy->addSrcInterface(NetDevice::ANY());
      }
      elseif (array_key_exists($zone, $this->fortigate->zones)) {
        $policy->addSrcInterface($this->fortigate->zones[$zone]);
      }
      elseif (array_key_exists($zone, $this->fortigate->interfaces)) {
        $policy->addSrcInterface($this->fortigate->interfaces[$zone]);
      }
      elseif (array_key_exists($zone, $this->fortigate->VPNs)) {
        $policy->addSrcInterface($this->fortigate->VPNs[$zone]);
      }
      else {
        throw new Exception("Source zone or interface $zone does not exist at row " . $row->getRowIndex(), 1);
      }
    }
    foreach (explode(" ", $destinationZone) as $zone) {
      if ($zone == "all") {
        $policy->addDstInterface(NetDevice::ANY());
      }
      elseif (array_key_exists($zone, $this->fortigate->zones)) {
        $policy->addDstInterface($this->fortigate->zones[$zone]);
      }
      elseif (array_key_exists($zone, $this->fortigate->interfaces)) {
        $policy->addDstInterface($this->fortigate->interfaces[$zone]);
      }
      elseif (array_key_exists($zone, $this->fortigate->VPNs)) {
        $policy->addDstInterface($this->fortigate->VPNs[$zone]);
      }
      else {
        throw new Exception("Destination zone or interface $zone does not exist at row " . $row->getRowIndex(), 1);
      }
    }
    if (empty($sourceAddress) || $sourceAddress == "all") {
      $policy->addSrcAddress(Address::ALL());
    }
    else {
      foreach (explode(" ", $sourceAddress) as $address) {
        if (array_key_exists($address, $this->fortigate->addressGroups)) {
          $policy->addSrcAddress($this->fortigate->addressGroups[$address]);
        }
        elseif (array_key_exists($address, $this->fortigate->addresses)) {
          $policy->addSrcAddress($this->fortigate->addresses[$address]);
        }
        else {
          throw new Exception("Source address or address group $address does not exist at row " . $row->getRowIndex(), 1);
        }
      }
    }
    if (empty($destinationAddress) || $destinationAddress == "all") {
      $policy->addDstAddress(Address::ALL());
    }
    else {
      foreach (explode(" ", $destinationAddress) as $address) {
        if (array_key_exists($address, $this->fortigate->addressGroups)) {
          $policy->addDstAddress($this->fortigate->addressGroups[$address]);
        }
        elseif (array_key_exists($address, $this->fortigate->addresses)) {
          $policy->addDstAddress($this->fortigate->addresses[$address]);
        }
        else {
          throw new Exception("Source address or address group $address does not exist at row " . $row->getRowIndex(), 1);
        }
      }
    }
    if (empty($service) || $service == "all") {
      $policy->addService(Service::ALL());
    }
    else {
      foreach (explode(" ", $service) as $svc) {
        if (array_key_exists($svc, $this->fortigate->serviceGroups)) {
          $policy->addService($this->fortigate->serviceGroups[$svc]);
        }
        elseif (array_key_exists($svc, $this->fortigate->services)) {
          $policy->addService($this->fortigate->services[$svc]);
        }
        else {
          throw new Exception("Sservice or service group $svc does not exist at row " . $row->getRowIndex(), 1);
        }
      }
    }
    if ($action == "allow" || empty($action)){
      $policy->setAction("accept");
    }
    else {
      $policy->setAction("deny");
    }
    if (!empty($nat)) {
      $policy->doNat();
    }
    $policy->setGlobalLabel($this->policySection);
    $this->fortigate->addPolicy($policy);
  }

  private function parsePolicy()
  {
    $sheet = $this->source->getSheetByName(self::TAB_POLICY);
    $this->policySection = "";
    foreach ($sheet->getRowIterator(3) as $row) {
      $this->addPolicy($row);
    }
  }

  public function getFortigate()
  {
    return $this->fortigate;
  }
}
