<?php

namespace Classes\User;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'tUser')]
class User
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: Manager::PRIMARY, type: Types::INTEGER)]
    private ?int $id = null;
    #[Column(name: Manager::EMAIL, type: Types::STRING)]
    private ?string $email = null;
    #[Column(name: Manager::LAST, type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $last = null;
    #[Column(name: Manager::TOKEN, type: Types::STRING)]
    private ?string $token = null;
    #[Column(name: Manager::PERMISSION, type: Types::STRING)]
    private ?string $permission = null;
    #[Column(name: Manager::STATE, type: Types::INTEGER)]
    private ?int $state = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getLast(): ?\DateTime
    {
        return $this->last;
    }

    public function setLast(?\DateTime $last): void
    {
        $this->last = $last;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function setPermission(?string $permission): void
    {
        $this->permission = $permission;
    }

    public function getState(): ?int
    {
        return $this->state;
    }

    public function setState(?int $state): void
    {
        $this->state = $state;
    }


}