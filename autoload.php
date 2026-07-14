<?php
    require_once 'vendor/autoload.php';
    function MYautoload($c)
    {

        if (!class_exists($c)) {
            $modulePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'presenters' . DIRECTORY_SEPARATOR . ucfirst($c) . '.php';

            $vendorClassPath = dirname(__FILE__).DIRECTORY_SEPARATOR . strtolower($c) . DIRECTORY_SEPARATOR . $c . '.php';
            if (file_exists($modulePath))
            {
                include $modulePath;
            } else if (file_exists($vendorClassPath))
            {
                include $vendorClassPath;
            }
        }
    }

    function PSR0Autoload($className)
    {
        if (!class_exists($className))
        {
            $className = ltrim($className, '\\');
            $fileName = dirname(__FILE__);
            $namespace = '';
            if ($lastNsPos = strripos($className, '\\'))
            {
                $namespace = strtolower(substr($className, 0, $lastNsPos));
                $className = substr($className, $lastNsPos + 1);
                $fileName .= DIRECTORY_SEPARATOR. str_replace('\\', DIRECTORY_SEPARATOR, $namespace) .
                    DIRECTORY_SEPARATOR;
            }
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

            // souborova struktura trid
            $fileName2root = dirname(__FILE__).DIRECTORY_SEPARATOR.'classes' . DIRECTORY_SEPARATOR;
            $fileName2 = $fileName2root . strtolower(str_replace('\\', DIRECTORY_SEPARATOR, $namespace)) .
                DIRECTORY_SEPARATOR . $className . '.php';

            if (file_exists($fileName))
            {
                include $fileName;
            } else if (file_exists($fileName2))
            {
                include $fileName2;
            }
        }
    }

    spl_autoload_register('PSR0Autoload', true, false);
    spl_autoload_register('MYautoload', true, false);
