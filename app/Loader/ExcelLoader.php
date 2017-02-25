<?php
namespace App\Loader;

use Exception;
use Fortinet\Fortigate\Address;
use Fortinet\Fortigate\AddressGroup;
use Fortinet\Fortigate\NetDevice;
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
  private $addresses = [];
  private $addressGroups = [];
  private $interfaces = [];
  private $zones = [];

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
          if (!array_key_exists($device, $this->interfaces)) {
            $this->interfaces[$device] = new NetDevice($device);
          }
        }
      }
      $ip = trim($row->getCellIterator()->seek("F")->current()->getValue());
      $mask = trim($row->getCellIterator()->seek("G")->current()->getValue());
      $alias = trim($row->getCellIterator()->seek("H")->current()->getValue());
      $vdom = trim($row->getCellIterator()->seek("I")->current()->getValue());
      $zone = trim($row->getCellIterator()->seek("J")->current()->getValue());
      if (!empty($zone) && !array_key_exists($zone, $this->zones)) {
        $this->zones[$zone] = new Zone();
        $this->zones[$zone]->setName($zone);
      }
      if (array_key_exists($name, $this->interfaces)) {
        throw new Exception("Duplicate interface $name at row " . $row->getRowIndex(), 1);
      }
      $if = new NetDevice($name);
      if ($type == "VLAN") {
        $if->setType(NetDevice::VLAN);
        $if->setVlanID($vlanid);
        $if->setVlanDevice($this->interfaces[$devices]);
      }
      if ($type == "LAGG") {
        $if->setType(NetDevice::LAGG);
        foreach ($aDevices as $device) {
          $if->addLaggNetDevice($this->interfaces[$device]);
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
      $this->zones[$zone]->addInterface($if);
      $this->interfaces[$if->getName()] = $if;
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
      if (array_key_exists($name, $this->addresses)) {
        throw new Exception("Duplicate address $name at row " . $row->getRowIndex(), 1);
      }
      $this->addresses[] = new Address($name, $ip, $mask);
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
      if (!array_key_exists($member, $this->addresses)) {
        throw new Exception("Cannot add non existent address $member in address group $name at row " . $row->getRowIndex(), 1);
      }
      if (!array_key_exists($name, $this->addressGroups)) {
        $this->addressGroups[$name] = new AddressGroup($name);
      }
      $this->addressGroups[$name]->addAddress($this->addresses[$member]);
    }
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

    $this->getInfos();
    $this->parseInterfaces();
    $this->parseAddress();
    $this->parseAddressGroup();
    // $this->parseService();
    // $this->parseServiceGroup();
    // $this->parsePolicy();
  }
}
