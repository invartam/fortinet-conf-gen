<?php
namespace App\Model;

use Exception;

class GetOverload {

  public function __get($property)
  {
    if (property_exists($this, $property)) {
      return $this->$property;
    }
    throw new Exception("Property $property does not exist", 1);
  }
}
