<?php

namespace OliviaLib;

use OliviaDatabaseLibrary\ADatabase;

class Database extends ADatabase
{
    public static function getDB($name = null)
    {
        $configuredDsn = trim(self::environment('OLIVIA_DB_DSN', ''));
        $dsnDriver = '';
        $dsnValues = [];
        if ($configuredDsn !== '') {
            if (preg_match('/^([a-z][a-z0-9_]*):(.*)$/i', $configuredDsn, $matches) !== 1) {
                throw new \RuntimeException('OLIVIA_DB_DSN is invalid.');
            }
            $dsnDriver = strtolower($matches[1]);
            if ($dsnDriver === 'mysql') {
                foreach (explode(';', $matches[2]) as $part) {
                    if ($part === '' || strpos($part, '=') === false) {
                        continue;
                    }
                    [$key, $value] = explode('=', $part, 2);
                    $key = strtolower(trim($key));
                    if (in_array($key, ['user', 'username', 'password'], true)) {
                        throw new \RuntimeException('Database credentials must not be embedded in OLIVIA_DB_DSN.');
                    }
                    if (in_array($key, ['host', 'port', 'dbname'], true) && !array_key_exists($key, $dsnValues)) {
                        $dsnValues[$key] = trim($value);
                    }
                }
            } elseif ($dsnDriver === 'sqlite') {
                $dsnValues['database'] = $matches[2];
            } else {
                throw new \RuntimeException('Unsupported database driver in OLIVIA_DB_DSN.');
            }
        }

        $explicitDriver = strtolower(trim(self::environment('DB_DRIVER', '')));
        if ($explicitDriver !== '' && $dsnDriver !== '' && $explicitDriver !== $dsnDriver) {
            throw new \RuntimeException('DB_DRIVER conflicts with OLIVIA_DB_DSN.');
        }
        $driver = $explicitDriver !== '' ? $explicitDriver : $dsnDriver;
        if ($driver !== '' && !in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new \RuntimeException('Unsupported database driver.');
        }

        $isSqlite = $driver === 'sqlite';
        $config = [
            'driver' => $driver,
            'host' => self::environment('DB_HOST', $dsnValues['host'] ?? ''),
            'port' => self::environment('DB_PORT', $dsnValues['port'] ?? ''),
            'database' => self::environment(
                'DB_DATABASE',
                $dsnValues['database'] ?? $dsnValues['dbname'] ?? ''
            ),
            'user' => self::environment('DB_USERNAME', self::environment('DB_USER', '')),
            'password' => self::environment('DB_PASSWORD', ''),
        ];

        if ($isSqlite) {
            $config['host'] = '';
            $config['port'] = '';
            $config['user'] = '';
            $config['password'] = '';
        }

        return $config;
    }

    private static function environment($name, $default = '')
    {
        $value = getenv($name);
        if ($value === false && isset($_ENV[$name])) {
            $value = $_ENV[$name];
        }
        if ($value === false && isset($_SERVER[$name])) {
            $value = $_SERVER[$name];
        }

        return $value === false || $value === null ? $default : (string) $value;
    }
}
