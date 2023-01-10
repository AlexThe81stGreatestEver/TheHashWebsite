<?php

namespace App\Entity;

class PasswordChangeTask {

  protected $currentPassword;
  protected $newPasswordInitial;
  protected $newPasswordConfirmation;

  public function getCurrentPassword() : string {
    return $this->currentPassword;
  }
  
  public function setCurrentPassword(string $currentPassword) {
    $this->currentPassword = $currentPassword;
  }
  
  public function getNewPasswordInitial() : string {
    return $this->newPasswordInitial;
  }
  
  public function setNewPasswordInitial(string $newPasswordInitial) {
    $this->newPasswordInitial = $newPasswordInitial;
  }

  public function getNewPasswordConfirmation() : string {
    return $this->newPasswordConfirmation;
  }
  
  public function setNewPasswordConfirmation(string $newPasswordConfirmation) {
    $this->newPasswordConfirmation = $newPasswordConfirmation;
  }
}
?>
