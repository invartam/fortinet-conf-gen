<?php
namespace App\Loader;

class SchoolLoader {

  private $source;
  private $schools = [];

  public function __construct($file)
  {
    if (!file_exists($file)) {
      throw new Exception("The file $file does not exist", 1);
    }
    $this->source = IOFactory::load($file);
  }

  private function parseSchool()
  {
    $sheet = $this->source->getSheet();
    
  }
}
