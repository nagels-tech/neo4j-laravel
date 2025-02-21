<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class Neo4jServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/neo4j.php',
            'neo4j'
        );

        // Bind PSR HTTP client interfaces
        $this->app->bind(HttpClientInterface::class, \GuzzleHttp\Client::class);
        $this->app->bind(RequestFactoryInterface::class, \GuzzleHttp\Psr7\HttpFactory::class);
        $this->app->bind(StreamFactoryInterface::class, \GuzzleHttp\Psr7\HttpFactory::class);

        $this->app->singleton(ClientFactory::class, function ($app) {
            $connections = collect(config('neo4j.connections'))->map(function ($config, $name) {
                if (!isset($config['url'])) {
                    throw new BindingResolutionException("The url configuration is required for Neo4j connection: {$name}");
                }

                return [
                    'alias' => $name,
                    'uri' => $config['url'],
                    'username' => $config['username'] ?? null,
                    'password' => $config['password'] ?? null,
                    'authentication' => $this->buildAuthConfig($config),
                    'driverConfig' => $this->buildDriverConfig($config),
                    'sessionConfig' => [
                        'database' => $config['database'] ?? 'neo4j',
                    ],
                    'transactionConfig' => $config['transaction'] ?? [
                        'timeout' => 30,
                    ],
                ];
            })->all();

            $defaultConnection = config('neo4j.connections.' . config('neo4j.default'));
            if (!$defaultConnection) {
                throw new BindingResolutionException('Default Neo4j connection is not configured');
            }

            return new ClientFactory(
                $this->buildDriverConfig($defaultConnection),
                ['database' => $defaultConnection['database'] ?? 'neo4j'],
                $defaultConnection['transaction'] ?? ['timeout' => 30],
                $connections,
                config('neo4j.default'),
                $app->make(HttpClientInterface::class),
                $app->make(StreamFactoryInterface::class),
                $app->make(RequestFactoryInterface::class),
                config('neo4j.logging.level'),
                $app->make('log')
            );
        });

        $this->app->singleton(ClientInterface::class, function ($app) {
            return $app->make(ClientFactory::class)->create();
        });

        $this->app->singleton(DriverInterface::class, function ($app) {
            return $app->make(ClientInterface::class)->getDriver(
                config('neo4j.default')
            );
        });

        $this->app->bind(SessionInterface::class, function ($app) {
            return $app->make(DriverInterface::class)->createSession();
        });

        $this->app->bind(TransactionInterface::class, function ($app) {
            return $app->make(SessionInterface::class)->beginTransaction();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/neo4j.php' => config_path('neo4j.php'),
            ], 'neo4j-config');
        }
    }

    /**
     * Build the authentication configuration.
     */
    protected function buildAuthConfig(array $config): array
    {
        $auth = [
            'scheme' => $config['auth_scheme'] ?? 'basic',
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
        ];

        if (isset($config['auth_token'])) {
            $auth['token'] = $config['auth_token'];
        }

        return $auth;
    }

    /**
     * Build the driver configuration.
     */
    protected function buildDriverConfig(array $config): array
    {
        $connection = $config['connection'] ?? [];
        $ssl = $config['ssl'] ?? [];

        return [
            'connectionTimeout' => $connection['timeout'] ?? 30,
            'maxPoolSize' => $connection['max_pool_size'] ?? 100,
            'ssl' => array_merge([
                'mode' => 'from_url',
                'verifyPeer' => true,
            ], $ssl),
        ];
    }
}
