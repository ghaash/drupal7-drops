<?php

namespace Apigee\Mint\DataStructures;

use \Apigee\Mint\Types\Country as Country;

class Address extends DataStructure {
  private $address1 = NULL;
  private $address2 = NULL;
  private $city = NULL;
  private $country = NULL;
  private $id = NULL;
  private $is_primary = NULL;
  private $state = NULL;
  private $zip = NULL;

  private $isPrimary;

  public function __construct($data = NULL) {
    if (is_array($data)) {
      $this->loadFromRawData($data);
    }
  }

  public function __toString() {
    return json_encode($this);
  }

  public function setAddress1($address1) {
    $this->address1 = $address1;
  }
  public function getAddress1() {
    return $this->address1;
  }

  public function setAddress2($address2) {
    $this->address2 = $address2;
  }
  public function getAddress2() {
    return $this->address2;
  }

  public function setCity($city) {
    $this->city = $city;
  }
  public function getCity() {
    return $this->city;
  }

  public function setCountry($country) {
    Country::validateCountryCode($country);
    $this->country = $country;
  }
  public function getCountry() {
    return $this->country;
  }

  public function setId($id) {
    $this->id = $id;
  }
  public function getId() {
    return $this->id;
  }

  public function setIsPrimary($is_primary) {
    $this->is_primary = $is_primary;
  }
  public function isPrimary() {
    return $this->is_primary;
  }

  public function setState($state) {
    $this->state = $state;
  }
  public function getState() {
    return $this->state;
  }

  public function setZip($zip) {
    $this->zip = $zip;
  }
  public function getZip() {
    return $this->zip;
  }
}