<?php
namespace App\Loader;

use Exception;
use Fortinet\Fortigate\Fortigate;
use Fortinet\Fortigate\NetDevice;
use Fortinet\Fortigate\Address;
use Fortinet\Fortigate\AddressGroup;
use Fortinet\Fortigate\Service;
use Fortinet\Fortigate\ServiceGroup;
use Fortinet\Fortigate\Zone;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelLoader {

  const TAB_INFOS = "Suivi";
  const TAB_INTERFACES = "Interfaces";
  const TAB_ADDRESS = "Adresses";
  const TAB_ADDRESSGROUP = "AdressesGroup";
  const TAB_SERVICE = "Services";
  const TAB_SERVICEGROUP = "ServicesGroup";
  const TAB_POLICY = "Policies";

  private $source;
  private $fortigate;

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

  private function parsePolicy()
  {

  }

  private function getFortigate()
  {
    return new Fortigate();
  }

  public function __construct($file)
  {
    if (!file_exists($file)) {
      throw new Exception("The file $file does not exist", 1);
    }
    $this->source = IOFactory::load($file);
    $this->fortigate = new Fortigate();

    $this->getInfos();
    $this->parseInterfaces();
    $this->parseAddress();
    $this->parseAddressGroup();
    $this->parseService();
    $this->parseServiceGroup();
    // $this->parsePolicy();
    print $this->fortigate;
  }
}
