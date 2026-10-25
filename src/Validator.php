<?php

namespace Bucky;

class Validator
{
    public static function bucketName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/', $name);
    }

    public static function sourceUrl(string $url): bool
    {
        if ($url === '-' || $url === '') {
            return true;
        }
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    public static function hostname(string $host): bool
    {
        if ($host === '-' || $host === '') {
            return true;
        }
        return (bool) preg_match('/^[a-z0-9.\-]+$/i', $host);
    }

    public static function resolveIp(string $hostname): string
    {
        if ($hostname === '-' || $hostname === '') {
            return '-';
        }
        return gethostbyname($hostname);
    }
}
