<?php

use Classes\StaticFunctions;

function moduleLoader($container, $urls = array())
    {
        $tmp = explode('-', $urls[1]);
        $class = implode('\\', array_map('ucfirst', $tmp)) . (count($tmp) > 1 ? $container::MODULE : '');
        try {
            $m1 = new ReflectionMethod ($class, 'getInstance');
        } catch (ReflectionException $e) {
            //dump($e);
        }
        try {
            $o = $m1->invoke(null, $container);
        } catch (ReflectionException $e) {
            //dump($e);
        }
        try {
            $m2 = new ReflectionMethod ($class, 'init');
        } catch (ReflectionException $e) {
            //dump($e);
        }
        try {
            $m2->invoke($o, $urls);
        } catch (ReflectionException $e) {
            //dump($e);
        }
    }

    function formatString(array $stringList)
    {
        $string = reset($stringList);
        if (count($stringList) > 1 && preg_match('/%(d|s)/u', $string))
        {
            $string = call_user_func_array('sprintf', $stringList);
        }
        return $string;
    }

    function myTrim(string $value)
    {
        $value = str_replace('<br><br>', 'SPACE_BR', $value);
        $value = str_replace('<br>', '', $value);
        return str_replace('SPACE_BR', '<br>', $value);
    }

    function swapAssociativeKeys (array &$array, $key1, $key2)
    {
        for ($i = 0; $i < sizeof($array); $i++)
        {
            if (array_keys($array)[$i] == $key1)
            {
                $key1at = $i;
            }
            if (array_keys($array)[$i] == $key2)
            {
                $key2at = $i;
            }
        }
        if ($key1at > $key2at)
        {
            $i = $key1at;
            $key1at = $key2at;
            $key2at = $i;
        }

        $order = array();
        for ($i = 0; $i < sizeof($array); $i++)
        {
            if ($i == $key1at)
            {
                $order[] = array_keys($array)[$key2at];
            }
            else
            {
                if ($i == $key2at)
                {
                    $order[] = array_keys($array)[$key1at];
                }
                else
                {
                    $order[] = array_keys($array)[$i];
                }
            }
        }

        $return = array();
        foreach ($order as $i)
        {
            $return[$i] = $array[$i];
        }
        return $return;
    }

    function preserveSpecialChars($string)
    {
        $encoded = urlencode($string);
        $specialChars = str_split('!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~');


        foreach ($specialChars as $char) {
            $encodedChar = urlencode($char);
            $encoded = str_replace($encodedChar, $char, $encoded);
        }

        return $encoded;
    }