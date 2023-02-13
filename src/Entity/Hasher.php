<?php

namespace App\Entity;

use App\Repository\HasherRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HasherRepository::class)]
#[ORM\Table(name: 'HASHERS')]
class Hasher
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $hasher_ky = null;

  #[ORM\Column(length: 90)]
  private ?string $hasher_name = null;

  #[ORM\Column(length: 45)]
  private ?string $hasher_abbreviation = null;

  #[ORM\Column(length: 45)]
  private ?string $last_name = null;

  #[ORM\Column(length: 45)]
  private ?string $first_name = null;

  #[ORM\Column(length: 45)]
  private ?string $home_kennel = null;

  #[ORM\Column()]
  private ?int $deceased = null;

  #[ORM\Column()]
  private ?int $banned = null;

  public function getHasherKy(): ?int {
    return $this->hasher_ky;
  }

  public function getHasherName(): ?string {
    return $this->hasher_name;
  }

  public function setHasherName(string $hasherName): self {
    $this->hasher_name = $hasherName;
    return $this;
  }

  public function getHasherAbbreviation(): ?string {
    return $this->hasher_abbreviation;
  }

  public function setHasherAbbreviation(string $hasherAbbreviation): self {
    $this->hasher_abbreviation = $hasherAbbreviation;
    return $this;
  }

  public function getLastName(): ?string {
    return $this->last_name;
  }

  public function setLastName(string $lastName): self {
    $this->last_name = $lastName;
    return $this;
  }

  public function getFirstName(): ?string {
    return $this->first_name;
  }

  public function setFirstName(string $firstName): self {
    $this->first_name = $firstName;
    return $this;
  }

  public function getHomeKennel(): ?string {
    return $this->home_kennel;
  }

  public function setHomeKennel(string $homeKennel): self {
    $this->home_kennel = $homeKennel;
    return $this;
  }

  public function getDeceased(): ?int {
    return $this->deceased;
  }

  public function setDeceased(int $deceased): self {
    $this->deceased = $deceased;
    return $this;
  }

  public function getBanned(): ?int {
    return $this->banned;
  }

  public function setBanned(int $banned): self {
    $this->banned = $banned;
    return $this;
  }
}
