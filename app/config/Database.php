<?php

namespace app\config;

use Dotenv\Dotenv;
use Flight;
use flight\database\PdoWrapper;
use flight\debug\database\PdoQueryCapture;
use Tracy\Debugger;

class Database
{
    private static $config = [
        // 'server1' => [
        //     'host' => 'localhost',
        //     'port' => 1433,
        //     'username' => 'user1',
        //     'password' => 'password1',
        //     'database' => 'db1',
        // ],
        // 'server2' => [
        //     'host' => 'localhost',
        //     'port' => 1433,
        //     'username' => 'user2',
        //     'password' => 'password2',
        //     'database' => 'db2',
        //     'options' => [ // SQL server options
        //         'encrypt' => true,
        //         'trustServerCertificate' => false,
        //         'connectionPooling' => true,
        //         'integratedSecurity' => true, // integrated security for Windows authentication
        //         'appName' => 'MCP SQL Server Client',
        //         'loginTimeout' => 30, // login timeout in seconds
        //         'queryTimeout' => 300, // query timeout in seconds
        //         'charset' => 'utf8', // character set
        //         'ssl' => true, // enable SSL
        //     ]
        // ]
    ];

    public static function getConfig($serverName)
    {
        if (isset(self::$config[$serverName])) {
            return self::$config[$serverName];
        }

        throw new \Exception("Configuração do servidor '{$serverName}' não encontrada.");
    }

    public static function getAllConfigs()
    {
        return self::$config;
    }

    public static function configureAll()
    {
        if (empty(self::$config)) {
            return;
        }
        $app = Flight::app();
        $pdoClass = Debugger::$showBar === true ? PdoQueryCapture::class : PdoWrapper::class;
        if(count(self::$config) == 1) {
            // If there's only one server, register it as 'db'
            $onlyConfig = reset(self::$config);
            try {
                $app->register('db', $pdoClass, [$onlyConfig['dsn'], $onlyConfig['username'] ?? null, $onlyConfig['password'] ?? null]);
            } catch (\Exception $e) {
                throw new \Exception("Error configuring the database: " . $e->getMessage());
            }
            return;
        }
        foreach (self::$config as $serverName => $config) {
            try {
                // Register the database connection with Flight
                $app->register('db_' . $serverName, $pdoClass, [$config['dsn'], $config['username'] ?? null, $config['password'] ?? null]);
            } catch (\Exception $e) {
                throw new \Exception("Error configuring database server '{$serverName}': " . $e->getMessage());
            }
        }
        
    }

    public static function loadEnvConfig() 
    {
        // load .env file
        if (!file_exists(ABSPATH . '.env')) {
            throw new \Exception('.env file not found. Please create a .env file in the root directory to get started.');
        }
        // Load environment variables from .env file
        // This will load variables like DB_localhost_dsn, DB_localhost_user, etc.
        // and populate the self::$config array accordingly
        // Example: DB_localhost_dsn=sqlsrv:Server=localhost,1433;Database=database
        //          DB_localhost_dsn=mysql:dbname=testdb;host=127.0.0.1;port=3333
        //          DB_localhost_user=user
        //          DB_localhost_password=password
        //          DB_localhost_options=encrypt=true;trustServerCertificate=false;connectionPooling=true
        $dotenv = Dotenv::createImmutable(ABSPATH);
        $dotenv->load();
        foreach ($_ENV as $key => $value) {
            if (0 === strpos($key, 'DB_')) {
                // $key = 'DB_localhost_dsn'
                $parts = explode('_', $key);
                if (count($parts) >= 3) {
                    $serverName = strtolower($parts[1]);
                    $configKey = implode('_', array_slice($parts, 2));
                    if (!isset(self::$config[$serverName])) {
                        self::$config[$serverName] = [];
                    }
                    self::$config[$serverName][$configKey] = $value;
                }
            }
        }

    }
}
