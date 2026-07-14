<?php
namespace Presenters;

use dibi;
use Dibi\Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;

class Container
{
    private function getInstance()
    {
        $trace = debug_backtrace();
        $trace = $trace[$trace[1]['function'] == 'getManager' ? 2 : 1];
        $reflectionMethod = new ReflectionMethod($trace['class'], $trace['function']);

        static $instanceList = [];
        try {
            $reflectionClass = new ReflectionClass ($this);
            $reflectionFunction = $reflectionClass->getMethod($reflectionMethod->getName());

            $class =  $reflectionFunction->getReturnType()->getName();

            if (!class_exists($class))
            {
                throw new RuntimeException($class . ' = does not exists');
            }

            if (!array_key_exists($class, $instanceList))
            {
                $reflection = new ReflectionClass($class);
                $instanceList[$class] = $reflection->newInstanceArgs(func_get_args());
            }
        } catch (\ReflectionException $e) {
            bdump('error');
            bdump($e);
        }
        return $instanceList[$class];
    }

    public function getDibi()
    {
        try {
            return dibi::getConnection();
        } catch (Exception $e) {
            dumpe($e);
        }
    }

    public function getPaginator(): \Classes\Super\Paginator
    {
        return $this->getInstance($this->getDibi());
    }

    public function getUserManager(): \Classes\User\Manager
    {
        return $this->getInstance($this->getDibi());
    }

    public function getUserFacade(): \Classes\User\Facade
    {
        return $this->getInstance($this->getUserManager());
    }

    public function getSettingsManager(): \Classes\Settings\Manager
    {
        return $this->getInstance($this->getDibi());
    }
}