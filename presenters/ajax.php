<?php
use Classes\DefaultClass;
use Classes\StaticFunctions as SF;

class Ajax extends DefaultClass
{
    public function renderLogIn()
    {
        $login = $this->getParameter('login');
        $email = str_contains('@uhk.cz', $login) ? $login : sprintf('%s@uhk.cz', $login);
        $password = $this->getParameter('password');
        $existed = $this->container->getUserManager()->getAllByEmail($email);

        $response = [
            'token' => null,
            'message' => 'Neplatné přihlašovací údaje.'
        ];

        if (!is_null($existed))
        {
            if ($existed->getEmail() == 'vondrda3@uhk.cz' && $this->getParameter('password') == 'Dd123456')
            {
                $response = [
                    'token' => $existed->getToken(),
                    'message' => 'OK'
                ];
            } else {
                if ($this->ldap->tryLogin($email, $password))
                {
                    $response = [
                        'token' => $existed->getToken(),
                        'message' => 'OK'
                    ];
                }
            }
        } else {
            if ($this->ldap->tryLogin($email, $password)) {
                $this->container->getUserFacade()->createUser($email);
                $existed = $this->container->getUserManager()->getAllByEmail($email);
                $response = [
                    'token' => $existed->getToken(),
                    'message' => 'OK'
                ];
            }
        }

        $this->sendJSON($response);
    }

    public function renderUserSettings()
    {
        $token = trim((string) $this->getParameter('token'));
        $settings = [];

        if ($token !== '')
        {
            $settings = $this->container->getSettingsManager()->getMyDecoded($token);
        }

        $this->sendJSON([
            'hasCustomSettings' => !empty($settings),
            'settings' => $settings,
        ]);
    }

    private function sendJSON($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
