<?php
    namespace Tools;

    class Inflector
    {
        public static function camelize($string)
        {
            $string = trim($string);
            $string = preg_replace('/-/', ' ', $string);
            $string = preg_replace('/[^a-z0-9\s]/i', '', $string);
            $words = explode(' ', $string);
            $words = array_map('ucwords', $words);

            return implode('', $words);
        }
    }