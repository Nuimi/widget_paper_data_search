<?php
namespace Classes;

use DateInterval;
use DateTime;

class DateTimeUtil extends DateTime
{
    const MONTHS = [
        1 => 'Leden',
        2 => 'Únor',
        3 => 'Březen',
        4 => 'Duben',
        5 => 'Květen',
        6 => 'Červen',
        7 => 'Červenec',
        8 => 'Srpen',
        9 => 'Září',
        10 => 'Říjen',
        11 => 'Listopad',
        12 => 'Prosinec',
    ];

    const MONTHS_REVERSE = [
        'Leden' => 1,
        'Únor' => 2,
        'Březen' => 3,
        'Duben' => 4,
        'Květen' => 5,
        'Červen' => 6,
        'Červenec' => 7,
        'Srpen' => 8,
        'Září' => 9,
        'Říjen' => 10,
        'Listopad' => 11,
        'Prosinec' => 12,
    ];

    public function __construct($date = '')
    {
        parent::__construct(($date instanceof DateTime) ? $date->format('Y-m-d H:i:s') : $date);
    }

    public function getDateTimeHumanFormat() : string
    {
        return $this->format('Y.m.d H:i:s');
    }

    public function getDateTimeFormat() : string
    {
        return $this->format('d.m.Y H:i:s');
    }

    public function getDateFormat() : string
    {
        return $this->format('Y.m.d');
    }

    public function getCZDateFormat() : string
    {
        return $this->format('d.m.Y');
    }

    public function getTimeFormat() : string
    {
        return $this->format('H:i');
    }

    public static function getAllMonths() : array
    {
        return self::MONTHS;
    }

    public static function getMonthNumber($month) : string
    {
        return self::MONTHS[$month];
    }

    public function getCalendarDateFormat(): string
    {
        return $this->format('Y-m-d\TH:i:s');
    }

    public function day(): string
    {
        return $this->format('d');
    }

    public function inputFormat(): string
    {
        return $this->format('Y-m-d\TH:i');
    }

    public function inputFormatDate(): string
    {
        return $this->format('Y-m-d');
    }

    public function getDay(): string
    {
        return $this->format('d');
    }

    public function getMonth(): string
    {
        return $this->format('m');
    }

    public function getCompareDate(): string
    {
        return $this->format('Ymd');
    }

    public function getCompareDateTime(): string
    {
        return $this->format('YmdHis');
    }

    public function addToMe(int $howMany, string $what)
    {
        $this->modify('+' . $howMany . ' ' . $what);
    }

    public function substractFromMe(int $howMany, string $what)
    {
        $this->modify('-' . $howMany . ' ' . $what);
    }
}