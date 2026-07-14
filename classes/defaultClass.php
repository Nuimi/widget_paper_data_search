<?php
namespace Classes;

use Classes\Super\LDAP;
use Classes\User\Manager;
use Presenters\Container;
use ReflectionClass;
use Tools\Inflector;

use Classes\StaticFunctions as SF;

class DefaultClass
{
    const ALERT_ERROR = 'danger';
    const ALERT_SUCCESS = 'success';

    const MENU_ADMIN = 1;
    const MENU_USER = 2;
    const MENU_EMPLOYEE_T = 3;
    const MENU_EMPLOYEE = 4;
    const MENU_CERTIFICATE = 5;

    const MODAL_USER = 1;
    const MODAL_EMPLOYEE_T = 2;
    const MODAL_LEADER = 3;
    const MODAL_CERTIFICATE= 4;

    const PRIMARY_LANGUAGE = 'CZ';

    const SL_USER = 'user';

    const DEFAULT_TRANSLATIONS = [
        'Veškeré nadcházející termíny na FIM' => 'All upcoming dates at FIM',
        'Ahoj, já jsem tvá FIM AI, copak byste chtěli vědět?' => "Hi, I'm your FIM AI, what would you like to know?",
        'Podrobné informace' => 'Detail information',
        'Zavřít' => 'Close',
        'Informace k ovládání stránky' => 'Page control information',
        'Aktuální informace' => 'News',
        'Více informací zde' => 'More information',
    ];

    public Container $container;
    public LDAP $ldap;
    protected $view, $reflection, $template;


    public function __construct(Container $container = null)
    {
        $this->container = $container;
        $this->ldap = new LDAP();
    }

    public static function getInstance($container = null)
    {
        static $instance = null;
        if ($instance === null)
        {
            $instance = new static($container);
            $instance->reflection = new \ReflectionClass($instance);
        }
        return $instance;
    }

    public function init($args)
    {
        unset($args[0]);
        unset($args[1]);
        if(array_key_exists(2, $args))
        {
            unset($args[1]);
        }

        $view = null;
        if (count($args) > 0)
        {
            $view = strtolower(array_shift($args));
        }

        if ($view == '')
        {
            $view = 'default';
        }

        $this->view = strtr($view, ['-' => '_']);
        $methodName = 'render' . Inflector::camelize($view);

        if ($this->reflection->hasMethod($methodName))
        {
            $result = $this->reflection->getMethod($methodName)->invokeArgs($this, $args);
            echo $result;

            exit;
        } else {
            if ($this->reflection->hasMethod('renderHome'))
            {
                $result = $this->reflection->getMethod('renderHome')->invokeArgs($this, $args);
                echo $result;

                exit;
            }
        }
    }

    /**
     * Navraci parametr z GET / POST
     * pokud je zadán klíč, vrátí pouze danou hodnotu
     * pokud není, vratí vše jak v POST tak i v GET
     *
     * @param null $key
     * @return array|mixed
     */
    public function getParameter($key = null)
    {
        $parameters = array_merge($_POST, $_GET);

        if ($key)
        {
            foreach ($parameters as $paramKey => $data)
            {
                if($key == $paramKey)
                {
                    return $data;
                }
            }
        }

        return $parameters;
    }

    public function renderLayout($file, $specificLatte = '', $refreshMessage = false): void
    {
        $latte = new \Latte\Engine;
        $latte->setTempDirectory('cache');
        $reflection = new ReflectionClass($this);

        $this->getDefaultData();
        $template = $this->getData();

        if($refreshMessage)
        {
            $template['messages'] = $this->getMessage();
        }

        $url = sprintf('www/%s/%s.latte', $specificLatte ?: strtolower($reflection->getName()), $file);
        $latte->render($url, $template);
    }

    private function getDefaultData(): void
    {
        if (is_null($this->template) || !array_key_exists('select2', $this->template))
        {
            $this->template['select2'] = false;
        }

        if (!array_key_exists('messages', $this->template))
        {
            $this->template['messages'] = $this->getMessage();
        }

        if ($this->checkLoggedIn())
        {
            $this->template['userData'] = [
                'userName' => UserData::getUserName(),
                'email' => UserData::getEmail(),
                'permission' => UserData::getPermission(),
                'permissionName' => Manager::P_DICTIONARY[UserData::getPermission()],
            ];
        }
    }

    public function addData($key, $data)
    {
        $this->template[$key] = $data;
    }

    public function getData(string $key = null)
    {
        if ($key)
        {
            return $this->template[$key];
        } else {
            return $this->template;
        }
    }

    private function getMessage() : array
    {
        $return = [];

        if (array_key_exists('alert', $_SESSION))
        {
            foreach ($_SESSION['alert'] as $message)
            {
                $data['severity'] = $message['severity'];
                $data['message'] = $message['message'];
                $data['title'] = $message['title'];
                $return[] = $data;
            }
        }

        unset($_SESSION['alert']);
        return $return;
    }

    public function checkLoggedIn(): bool
    {
        return array_key_exists('email', $_SESSION);
    }

    public function isLoggedIn(): void
    {

        if (!$this->checkLoggedIn())
        {
            SF::addErrorMessage('Je potřeba se přihlásit');
            SF::setHeader('/user/signIn');
        }
    }

    public function isRequest(): bool
    {
        if ($_POST || $_GET)
        {
            return true;
        }
        return false;
    }

    public function setActiveMenu(int $menuID) : void
    {
        $this->addData('activeMenu', $menuID);
    }

    public function setActiveModal(int $modalID) : void
    {
        $this->addData('modal', $modalID);
    }

    public function useSelect2() : void
    {
        $this->addData('select2', true);
    }

    public function clearFilters() : void
    {
        new Filters();
        new Ordering();
        if (array_key_exists('showAll', $_GET) && $_GET['showAll'] == 'true')
        {
            Filters::setShowAll(true);
        } else {
            Filters::setShowAll(false);
        }
    }
}
