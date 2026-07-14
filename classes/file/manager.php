<?php

namespace Classes\File;

use Classes\Dibi;

class Manager extends Dibi
{
    const TABLE = 'tFile';
    const PRIMARY = 'id';

    const PATH = 'path';
    const NAME = 'name';
    const TYPE = 'type';
    const ADDED = 'added';

}