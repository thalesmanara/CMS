<?php

declare(strict_types=1);

namespace Revita\Crm\Helpers;

final class Slugger
{
    public static function slugify(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $text = mb_strtolower($text, 'UTF-8');
        $text = self::removeAccents($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? $text;
        $text = preg_replace('/[\s-]+/', '-', $text) ?? $text;
        $text = trim($text, '-');
        return $text;
    }

    private static function removeAccents(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        return strtr($text, $map);
    }
}

