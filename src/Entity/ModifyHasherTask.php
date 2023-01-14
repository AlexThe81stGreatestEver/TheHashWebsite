<?php

namespace App\Entity;

class ModifyHasherTask {

  protected $hasherName;
  protected $hasherAbbreviation;
  protected $lastName;
  protected $firstName;
  protected $homeKennel;
  protected $banned;
  protected $deceased;

  public function getDeceased() : int {
    return $this->deceased;
  }
  
  public function setDeceased(int $deceased) {
    $this->deceased = $deceased;
  }

  public function getBanned() : int {
    return $this->banned;
  }
  
  public function setBanned(int $banned) {
    $this->banned = $banned;
  }

  public function getHomeKennel() : string {
    return $this->homeKennel;
  }
  
  public function setHomeKennel(?string $homeKennel) {
    $this->homeKennel = $homeKennel;
  }

  public function getFirstName() : string {
    return $this->firstName;
  }
  
  public function setFirstName(?string $firstName) {
    $this->firstName = $firstName;
  }

  public function getLastName() : string {
    return $this->lastName;
  }
  
  public function setLastName(?string $lastName) {
    $this->lastName = $lastName;
  }

  public function getHasherAbbreviation() : string {
    return $this->hasherAbbreviation;
  }
  
  public function setHasherAbbreviation(?string $hasherAbbreviation) {
    $this->hasherAbbreviation = $hasherAbbreviation;
  }

  public function getHasherName() : string {
    return $this->hasherName;
  }
  
  public function setHasherName(string $hasherName) {
    $this->hasherName = $hasherName;
  }
}
?>
