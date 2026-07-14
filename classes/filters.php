<?php

namespace Classes;


class Filters
{
    public function __construct()
    {
        $_SESSION['filters'] = [
            'search' => null,
            'workPlace' => null,
            'from' => null,
            'to' => null,
            'cert' => null,
            'provider' => null,
            'showAll' => false,
        ];
    }

    public static function getSearch(): ?string
    {
        return $_SESSION['filters']['search'];
    }

    public static function setSearch(?string $search): void
    {
        $_SESSION['filters']['search'] = $search;
    }

    public static function getWorkPlace(): ?int
    {
        return $_SESSION['filters']['workPlace'];
    }

    public static function setWorkPlace(?int $workPlace): void
    {
        $_SESSION['filters']['workPlace'] = $workPlace;
    }

    public static function getFrom(): ?DateTimeUtil
    {
        return $_SESSION['filters']['from'];
    }

    public static function setFrom(?DateTimeUtil $from): void
    {
        $_SESSION['filters']['from'] = $from;
    }

    public static function getTo(): ?DateTimeUtil
    {
        return $_SESSION['filters']['to'];
    }

    public static function setTo(?DateTimeUtil $to): void
    {
        $_SESSION['filters']['to'] = $to;
    }

    public static function getCert(): ?string
    {
        return $_SESSION['filters']['cert'];
    }

    public static function setCert(?string $to): void
    {
        $_SESSION['filters']['cert'] = $to;
    }

    public static function getProvider(): ?string
    {
        return $_SESSION['filters']['provider'];
    }

    public static function setProvider(?string $to): void
    {
        $_SESSION['filters']['provider'] = $to;
    }

    public static function getShowAll(): ?bool
    {
        return $_SESSION['filters']['showAll'];
    }

    public static function setShowAll(?bool $showAll): void
    {
        $_SESSION['filters']['showAll'] = $showAll;
    }

    public static function getAll(): array
    {
        return [
            'search' => self::getSearch(),
            'workPlace' => self::getWorkPlace(),
            'from' => self::getFrom(),
            'to' => self::getTo()
        ];
    }
}