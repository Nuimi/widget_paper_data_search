<?php

namespace Classes\Super;

use Exception;

class LDAP
{

    public function __construct()
    {
    }

    public function tryLogin(string $userName, string $password): bool
    {
        $ldap_connection = ldap_connect("172.25.4.10");

        if (FALSE === $ldap_connection) {
            return false;
        } else {
            ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3) or die('Unable to set LDAP protocol version');
            ldap_set_option($ldap_connection, LDAP_OPT_REFERRALS, 0);
            try {

                $result = ldap_bind($ldap_connection, $userName, $password);
            } catch(Exception $e) {
                bdump($e);
                return false;
            }
            if (!$result) {
                $errno = ldap_errno($ldap_connection);
                $error = ldap_error($ldap_connection);
                bdump("LDAP bind failed with error code $errno: $error");
                return false;
            } else {
                return true;
            }
        }
    }
}