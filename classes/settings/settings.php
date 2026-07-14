<?php

namespace Classes\Settings;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'tSettings')]
class Settings
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: Manager::PRIMARY, type: Types::INTEGER)]
    private ?int $id = null;
    #[Column(name: Manager::USER, type: Types::INTEGER)]
    private ?string $user = null;
    #[Column(name: Manager::SETTINGS, type: Types::STRING)]
    private ?string $settings = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setUser(?string $user): void
    {
        $this->user = $user;
    }

    public function getSettings(): ?string
    {
        return $this->settings;
    }

    public function setSettings(?string $settings): void
    {
        $this->settings = $settings;
    }


}