<?php

namespace App\Config;

use Symfony\Component\Yaml\Yaml;

class ConfigFile
{
    public static function get(string $filename, callable $createIfMissing, string $dir = null): array | false
    {
        $path = self::path($filename, $dir);

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        $data = $createIfMissing($path);

        if (!$data) {
            return false;
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        return self::get($filename, $createIfMissing, $dir);
    }

    public static function getYaml(string $filename, callable $createIfMissing, string $dir = null): array | false
    {
        $path = self::path($filename, $dir);

        if (file_exists($path)) {
            return Yaml::parse(file_get_contents($path));
        }

        $data = $createIfMissing($path);

        if (!$data) {
            return false;
        }

        file_put_contents($path, Yaml::dump($data, 100));

        return self::getYaml($filename, $createIfMissing, $dir);
    }

    public static function path(string $filename, string $dir = null): string
    {
        $dir = $dir ?: exec('pwd');

        return "{$dir}/{$filename}";
    }
}
