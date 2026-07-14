<?php

namespace Classes\File;

use Classes\File\Manager;
use Classes\DateTimeUtil;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'tFile')]
class File
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: Manager::PRIMARY, type: Types::INTEGER)]
    private ?int $id = null;
    #[Column(name: Manager::PATH, type: Types::STRING)]
    private ?string $path = null;
    #[Column(name: Manager::NAME, type: Types::STRING)]
    private ?string $name = null;
    #[Column(name: Manager::TYPE, type: Types::STRING)]
    private ?string $type = null;
    #[Column(name: Manager::ADDED, type: Types::DATETIME_MUTABLE)]
    private ?DateTime $added = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getAdded(): ?DateTime
    {
        return $this->added;
    }

    public function setAdded(?DateTime $added): void
    {
        $this->added = $added;
    }


}