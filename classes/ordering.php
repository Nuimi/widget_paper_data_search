<?php

namespace Classes;


class Ordering
{
    public function __construct()
    {
        $_SESSION['ordering'] = [];
        $_SESSION['temp'] = '';
    }

    public static function getOrder(string $order): string
    {
        return array_key_exists($order, $_SESSION['ordering']) ? $_SESSION['ordering'][$order] : 'asc';
    }

    public static function isset(string $order): string
    {
        return array_key_exists($order, $_SESSION['ordering']);
    }

    public static function setOrder(string $order, string $direction): void
    {
        if ($_SESSION['temp'] != $order)
        {
            $_SESSION['ordering'] = [];
            $_SESSION['temp'] = $order;
        }

        $_SESSION['ordering'][$order] = $direction;
    }

    public static function getAll(): array
    {
        return array_map(function ($direction) {
            return $direction;
        }, $_SESSION['ordering']);
    }
}