<?php

namespace Classes\User;

use Classes\DateTimeUtil;
use Classes\StaticFunctions;
use Classes\User\Manager as UManager;

class Facade
{
    private UManager $userManager;

    public function __construct(UManager $userManager)
    {
        $this->userManager = $userManager;
    }

    public function createUser($email): void
    {

        try {
            $user = new User();
            $user->setEmail($email);
            $user->setLast(new DateTimeUtil());
            $user->setToken(bin2hex(random_bytes(32)));
            $user->setPermission(Manager::P_USER);
            $user->setState(Manager::ACTIVE);
            $this->userManager->saveEntity($user);
        } catch (\Random\RandomException $e) {
            bdump($e->getMessage());
        }
    }
}