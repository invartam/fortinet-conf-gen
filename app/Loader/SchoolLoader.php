<?php
namespace App\Loader;

use Exception;
use App\Model\School;
use App\Model\Subnet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SchoolLoader {

  private $source;
  private $schools = [];

  public function __construct($file)
  {
    if (!file_exists($file)) {
      throw new Exception("The file $file does not exist", 1);
    }
    $this->source = IOFactory::load($file);
    $this->parseSchool();
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
    // foreach ($this->schools as $key => $value) {
    //   print "$key\n";
    // }
  }

  public function getSchools()
  {
    return $this->schools;
  }
}
