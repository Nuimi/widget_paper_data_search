<?php

namespace Classes\File;

use Classes\DateTimeUtil;

class Facade
{
    private Manager $manager;
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }


    public function uploadOne(array $fileData): int
    {
        $id = 0;
        foreach ($fileData as $item)
        {
            $file[Manager::PATH] = $item['path'];
            $file[Manager::NAME] = $item['name'];
            $file[Manager::TYPE] = $item['type'];
            $file[Manager::ADDED] = new DateTimeUtil();

            $id = $this->manager->managerInsert($file, true);
            break;
        }

        return $id;
    }
}