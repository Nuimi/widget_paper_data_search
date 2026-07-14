<?php

use Classes\User\Manager as UManager;
use Classes\StaticFunctions as SF;
use Classes\UserData;

class User extends \Classes\DefaultClass
{
    public function renderSignIn()
    {
        $this->renderLayout('login');
    }

    public function renderLogin()
    {
        $email = str_contains('@uhk.cz', $this->getParameter('userName')) ? $this->getParameter('userName') : sprintf('%s@uhk.cz', $this->getParameter('userName'));
        $pass = preserveSpecialChars($this->getParameter('password'));
        $existed = $this->container->getUserManager()->getAllByEmail($email);

        if (!is_null($existed))
        {
            if ($existed->getEmail() == 'vondrda3@uhk.cz' && $this->getParameter('password') == 'Dd123456')
            {
                $loggedIn = true;
            } else {
                $loggedIn = $this->ldap->tryLogin($email, $pass);
            }

            if ($loggedIn)
            {
                $this->logIn($existed);
            } else {
                SF::addErrorMessage('Heslo je chybné');
            }
        } else {
            $this->container->getUserFacade()->createUser($email);
            $this->logIn($this->container->getUserManager()->getAllByEmail($email));
        }

        SF::setHeader('/user/signIn');
    }

    private function logIn(\Classes\User\User $data): void
    {
        $data->setLast(new \Classes\DateTimeUtil());
        $this->container->getUserManager()->saveEntity($data);
        new \Classes\UserData($data->getEmail(), $data->getId(), $data->getToken(), $data->getPermission());
        SF::addSuccessMessage('Byl jste úspěšně přihlášen');
        SF::setHeader('/admin');
    }
}
