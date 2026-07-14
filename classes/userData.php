<?php

namespace Classes;


class UserData
{
    public function __construct(string $email, int $id, string $token, string $permission)
    {
        $_SESSION['email'] = $email;
        $_SESSION['userID'] = $id;
        $_SESSION['token'] = $token;
        $_SESSION['permission'] = $permission;
    }

    public static function getEmail(): ?string
    {
        return (array_key_exists('email', $_SESSION))? $_SESSION['email'] : null;
    }

    public static function getUserID() : ?int
    {
        $id =  (array_key_exists('userID', $_SESSION))? $_SESSION['userID'] : null;
        if ($id == null) {
            StaticFunctions::setHeader('/user/logout?sorry=true');
        } else {
            return $id;
        }
    }

    public static function getToken() : string
    {
        return (array_key_exists('token', $_SESSION))? $_SESSION['token'] : '';
    }



    public static function getPermission() : string
    {
        return (array_key_exists('permission', $_SESSION))? $_SESSION['permission'] : '';
    }




    public static function getAll(): array
    {
        return [
            'id' => self::getUserID(),
            'email' => self::getEmail(),
            'token' => self::getToken(),
        ];
    }

    public static function getUserName(): array|string
    {
        return str_replace('@uhk.cz', '', $_SESSION['email']);
    }
}