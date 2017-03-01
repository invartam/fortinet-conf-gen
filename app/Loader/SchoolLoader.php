<?php
namespace App\Loader;

use Exception;
use App\Model\School;
use App\Model\Subnet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SchoolLoader {

  private $source;
  private $schools = [];
  private $servers = [];

  public function __construct($file)
  {
    if (!file_exists($file)) {
      throw new Exception("The file $file does not exist", 1);
    }
    $this->source = IOFactory::load($file);
    $this->parseSchool();
    $this->parseServers();
  }

  private function parseSchool()
  {
    $sheet = $this->source->getSheet();
    foreach ($sheet->getRowIterator(2) as $row) {
      $name = trim($row->getCellIterator()->seek("A")->current()->getValue());
      if (empty($name)) {
        break;
      }
      $vrfName = trim($row->getCellIterator()->seek("B")->current()->getCalculatedValue());
      if (!preg_match("/VRF_/", $vrfName) && !preg_match("/DMZ_/", $vrfName)) {
        continue;
      }
      $netName = trim($row->getCellIterator()->seek("D")->current()->getValue());
      $netAddr = trim($row->getCellIterator()->seek("F")->current()->getValue());
      $netMask = trim($row->getCellIterator()->seek("K")->current()->getValue());
      $shortName = trim($row->getCellIterator()->seek("M")->current()->getValue());
      if (!array_key_exists($shortName, $this->schools)) {
        $this->schools[$shortName] = new School($shortName);
      }
      $this->schools[$shortName]->addSubnet($vrfName, new Subnet($netName, $netAddr, $netMask));
    }
  }

  public function parseServers()
  {
    $sheet = $this->source->getSheetByName("Servers");
    foreach ($sheet->getRowIterator(2) as $row) {
      $name = trim($row->getCellIterator()->seek("A")->current()->getValue());
      if (empty($name)) {
        break;
      }
      $vrfName = trim($row->getCellIterator()->seek("B")->current()->getCalculatedValue());
      $netName = trim($row->getCellIterator()->seek("C")->current()->getCalculatedValue());
      $startOffset = trim($row->getCellIterator()->seek("D")->current()->getCalculatedValue());
      $endOffset = trim($row->getCellIterator()->seek("E")->current()->getCalculatedValue());
      foreach ($this->schools as $schoolName => $school) {
        $serverName = "H-$schoolName-$name";
        $chunks = explode(".", $school->subnets[$vrfName][$netName]->netip);
        $chunks[3] += $startOffset;
        $serverAddr = implode(".", $chunks);
        if (empty($endOffset)) {
          $server = new Subnet($serverName, $serverAddr);
        }
        else {
          $chunks[3] += ($endOffset - $startOffset);
          $serverEndAddr = implode(".", $chunks);
          $server = new Subnet($serverName, $serverAddr, $serverEndAddr, Subnet::TYPE_RANGE);
        }
        $this->servers[$name][$server->name] = $server;
      }
    }
  }

  public function getSchools()
  {
    return $this->schools;
  }

  public function getServers()
  {
    return $this->servers;
  }
}
