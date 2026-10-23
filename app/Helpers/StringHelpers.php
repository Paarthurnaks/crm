<?php

namespace App\Helpers;

class StringHelpers
{
    /**
     * Возвращает часть строки после первого вхождения указанной подстроки.
     */
    public static function afterFirst(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr($subject, $pos + strlen($search));
    }

    /**
     * Возвращает часть строки до первого вхождения указанной подстроки.
     */
    public static function beforeFirst(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr($subject, 0, $pos);
    }
}
