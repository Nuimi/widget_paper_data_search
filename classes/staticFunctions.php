<?php
namespace Classes;


use JetBrains\PhpStorm\NoReturn;

class StaticFunctions
{
    public static function crypt($first, $second): string
    {
        return hash_hmac("SHA512", $first, $second);
    }

    public static function passwordEncrypt($length = 50): string
    {
        $alphabet = '1234567890abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array();
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++)
        {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass);
    }

    public static function addSuccessMessage(string $message) : void
    {
        $array['message'] = $message;
        $array['severity'] = DefaultClass::ALERT_SUCCESS;
        $array['title'] = 'Skvěle';
        $_SESSION['alert'][] = $array;
    }

    public static function addErrorMessage(string $message) : void
    {
        $array['message'] = $message;
        $array['severity'] = DefaultClass::ALERT_ERROR;
        $array['title'] = 'Sakryš!!';
        $_SESSION['alert'][] = $array;
    }

    public static function renderLatte(string $file, array $data, string $specific = 'www/admin/tables') : string
    {
        $latte = new \Latte\Engine();
        return $latte->renderToString(sprintf('%s/%s.latte', $specific, $file), $data);
    }

    #[NoReturn] public static function setHeader($location): void
    {
        header('Location: '. StaticFunctions::getCurrentURL() . $location);
        exit;
    }
    public static function getCurrentURL(): string
    {
        preg_match('~^([^?]+)~', $_SERVER['REQUEST_URI'], $url);
        $urls = explode('/', $url[1]);
        if ($urls[1] == ACTUAL_SPACE)
        {
            return '/'.ACTUAL_SPACE;
        } else {
            return '';
        }
    }

    public static function getCSRFToken(): string
    {
        return $_SESSION['csrf_token'];
    }
}