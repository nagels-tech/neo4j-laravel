<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use Laudis\Neo4j\Common\Uri;

/** @psalm-suppress UnusedClass */
final class Neo4jServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, function (Application $app): ClientInterface {
            $config = $app->make('config');
            $defaultConnection = $config->get('database.default');
            $connections = $config->get('database.connections');
            
            // Prepare connections for the factory
            $factoryConnections = [];
            $defaultFound = false;
            
            // Format connections for the ClientFactory
            foreach ($connections as $name => $connection) {
                if (isset($connection['driver']) && $connection['driver'] === 'neo4j') {
                    // Validate the connection
                    $this->validateConnection($name, $connection);
                    
                    // Convert Laravel connection format to factory format
                    $factoryConnections[] = $this->formatConnectionForFactory($name, $connection);
                    
                    // Check if default connection is configured
                    if ($name === $defaultConnection) {
                        $defaultFound = true;
                    }
                }
            }
            
            if (!$defaultFound) {
                throw new BindingResolutionException("Default Neo4j connection '$defaultConnection' is not configured or invalid");
            }
            
            // Get logger if available
            $logger = null;
            $logLevel = null;
            
            if ($app->bound('log')) {
                $logger = $app->make('log');
                $logLevel = $config->get('database.neo4j.log_level', 'debug');
            }
            
            // Create the client factory
            $factory = new ClientFactory(
                null, // Default driver config
                null, // Default session config
                null, // Default transaction config
                $factoryConnections,
                $logLevel,
                $logger,
                $defaultConnection // Default driver
            );
            
            // Create the client
            $client = $factory->create();
            
            return $client;
        });

        $this->app->singleton(DriverInterface::class, function (Application $app): DriverInterface {
            return $app->make(ClientInterface::class)->getDriver(
                $app->make('config')->get('database.default')
            );
        });

        $this->app->bind(SessionInterface::class, function (Application $app): SessionInterface {
            return $app->make(DriverInterface::class)->createSession();
        });

        $this->app->bind(TransactionInterface::class, function (Application $app): TransactionInterface {
            return $app->make(SessionInterface::class)->beginTransaction();
        });

        // Register the Neo4j connection with Laravel's database manager
        $manager = $this->app->make('db');
        $manager->extend('neo4j', function (array $config, string $name) {
            $client = $this->app->make(ClientInterface::class);
            
            // Store the connection name in the config
            $config['name'] = $name;

            return new Neo4jConnection($client, $config['database'] ?? 'neo4j', '', $config);
        });
    }

    public function boot(): void
    {
    }
    
    /**
     * Format a Laravel connection configuration for use with the ClientFactory
     */
    private function formatConnectionForFactory(string $name, array $connection): array
    {
        // Build the URL if not provided
        $url = $connection['url'] ?? sprintf(
            'bolt://%s:%s',
            $connection['host'] ?? 'localhost',
            $connection['port'] ?? 7687
        );
        
        $formattedConnection = [
            'alias' => $name,
            'uri' => $url,
            'username' => $connection['username'] ?? '',
            'password' => $connection['password'] ?? '',
        ];
        
        // Add database if specified
        if (isset($connection['database'])) {
            $formattedConnection['session_config'] = [
                'database' => $connection['database']
            ];
        }
        
        // Add authentication if custom scheme is specified
        if (isset($connection['auth_scheme']) && $connection['auth_scheme'] !== 'basic') {
            $formattedConnection['authentication'] = [
                'scheme' => $connection['auth_scheme']
            ];
            
            // Add auth specific parameters
            if ($connection['auth_scheme'] === 'kerberos' && isset($connection['ticket'])) {
                $formattedConnection['authentication']['ticket'] = $connection['ticket'];
            } elseif ($connection['auth_scheme'] === 'oidc') {
                $formattedConnection['authentication']['token'] = $connection['auth_token'] ?? $connection['token'] ?? '';
            } elseif ($connection['auth_scheme'] === 'basic') {
                $formattedConnection['authentication']['username'] = $connection['username'] ?? '';
                $formattedConnection['authentication']['password'] = $connection['password'] ?? '';
            }
        }
        
        // Add driver configuration if specified
        if (isset($connection['connection']) || isset($connection['ssl'])) {
            $driverConfig = [];
            
            if (isset($connection['connection']['max_pool_size'])) {
                $driverConfig['max_pool_size'] = $connection['connection']['max_pool_size'];
            }
            
            if (isset($connection['connection']['timeout'])) {
                $driverConfig['connection_timeout'] = $connection['connection']['timeout'];
            }
            
            if (isset($connection['ssl'])) {
                $driverConfig['ssl'] = $connection['ssl'];
            }
            
            if (!empty($driverConfig)) {
                $formattedConnection['driver_config'] = $driverConfig;
            }
        }
        
        return $formattedConnection;
    }

    private function validateConnection(string $name, array $config): void
    {
        if (! isset($config['driver']) || $config['driver'] !== 'neo4j') {
            throw new BindingResolutionException("Invalid driver for Neo4j connection: {$name}");
        }

        if (! isset($config['url']) && (! isset($config['host']) || ! isset($config['port']))) {
            throw new BindingResolutionException("Missing required URL or host/port configuration for Neo4j connection: {$name}");
        }
    }
}
