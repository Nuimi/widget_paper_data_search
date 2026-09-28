<?php
use Classes\DefaultClass;
use Classes\Security\ApiToken;


class Ajax extends DefaultClass
{
    public function renderLogIn()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $this->sendJSON(['error' => 'Method not allowed'], 405);
        }
        $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($login === '' || $password === '') {
            $this->sendJSON(['message' => 'Fill login and password.'], 400);
        }
        $email = str_contains($login, '@') ? $login : $login . '@uhk.cz';
        try {
            if (!$this->ldap->tryLogin($email, $password)) {
                $this->sendJSON(['message' => 'Wrong credentials.'], 401);
            }
            $user = $this->container->getUserManager()->getAllByEmail($email);
            if ($user === null) {
                $this->container->getUserFacade()->createUser($email);
                $user = $this->container->getUserManager()->getAllByEmail($email);
            }
            if ($user === null || $user->getState() !== 1) {
                $this->sendJSON(['message' => 'Account is not active.'], 403);
            }
            $this->sendJSON(ApiToken::fromConfig()->issue($user->getId()) + ['message' => 'OK']);
        } catch (Throwable $error) {
            error_log('Q-Finder login unavailable: ' . get_class($error));
            $this->sendJSON(['message' => 'Authentication service unavailable.'], 503);
        }
    }

    public function renderUserSettings()
    {
        $token = ApiToken::bearer();
        if ($token === '') {
            $this->sendJSON(['error' => 'Authentication required.'], 401);
        }
        try {
            $user = ApiToken::fromConfig()->authenticate($token);
            if ($user === null) {
                $this->sendJSON(['error' => 'Session expired or revoked.'], 401);
            }
            $this->sendJSON(['hasCustomSettings' => !empty($user['settings']), 'settings' => $user['settings'],
                'expiresAt' => (int) $user['expiresAt']]);
        } catch (Throwable $error) {
            error_log('Q-Finder settings unavailable: ' . get_class($error));
            $this->sendJSON(['error' => 'Authentication service unavailable.'], 503);
        }
    }

    public function renderRevokeToken()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $this->sendJSON(['error' => 'Method not allowed'], 405);
        }
        $token = ApiToken::bearer();
        if ($token === '') {
            $this->sendJSON(['error' => 'Authentication required.'], 401);
        }
        try {
            // Idempotent for expired, revoked, or unknown tokens. No account details disclosed.
            ApiToken::fromConfig()->revoke($token);
            $this->sendJSON(['ok' => true]);
        } catch (Throwable $error) {
            error_log('Q-Finder revocation unavailable: ' . get_class($error));
            $this->sendJSON(['error' => 'Could not revoke the session. Please retry.'], 503);
        }
    }

    private function sendJSON(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
        exit;
    }
}
