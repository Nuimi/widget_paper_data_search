<?php

namespace Classes\Super;


class Paginator
{
    private \Dibi\Connection $db;
    private int $limit;
    private int $numberOfPages;

    public function __construct($db)
    {
        $this->db = $db;
        $this->numberOfPages = 1;
        $this->setLimit();
    }

    public function setQueryLimit(\Dibi\Fluent &$query, int $offset = 1) : void
    {
        $this->numberOfPages = $query->count();
        $query->limit($this->limit)->offset($offset);
    }

    public function getOffset(int $page) : int
    {
        $page = ($page <= 0)? 1 : $page;
        return ($page - 1) * $this->limit;
    }

    public function getLimit() : int
    {
        return $this->limit;
    }

    public function setLimit(int $limit = 20) : void
    {
        $this->limit = $limit;
    }

    public function getNumberOfPages() : int
    {
        return ceil($this->numberOfPages / $this->limit);
    }

    public function getNumberOfPagesWithDots(int $numberOfRows, int $limit, $page) : int
    {
        $number = ceil($numberOfRows / $limit);

        dump($numberOfRows);
        dump($limit);
        die;

    }
}