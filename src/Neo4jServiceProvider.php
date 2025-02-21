<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/** @psalm-suppress UnusedClass */
final class Neo4jServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/neo4j.php',
            'neo4j'
        );

        $this->app->bind(HttpClientInterface::class, \GuzzleHttp\Client::class);
        $this->app->bind(RequestFactoryInterface::class, \GuzzleHttp\Psr7\HttpFactory::class);
        $this->app->bind(StreamFactoryInterface::class, \GuzzleHttp\Psr7\HttpFactory::class);

        $this->app->singleton(ClientFactory::class, function (Application $app): ClientFactory {
            $connections = collect(config('neo4j.connections'))->map(function ($config, $name) {
                if (! isset($config['url'])) {
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
            if (! $defaultConnection) {
                throw new BindingResolutionException('Default Neo4j connection is not configured');
            }

            return new ClientFactory(
                $this->buildDriverConfig($defaultConnection),
                ['database' => $defaultConnection['database'] ?? 'neo4j'],
                $defaultConnection['transaction'] ?? ['timeout' => 30],
                $connections,
                $app->make(HttpClientInterface::class),
                $app->make(StreamFactoryInterface::class),
                $app->make(RequestFactoryInterface::class),
                config('neo4j.logging.level'),
                $app->make('log')
            );
        });

        $this->app->singleton(ClientInterface::class, function (Application $app): ClientInterface {
            return $app->make(ClientFactory::class)->create();
        });

        $this->app->singleton(DriverInterface::class, function (Application $app): DriverInterface {
            return $app->make(ClientInterface::class)->getDriver(
                config('neo4j.default')
            );
        });

        $this->app->bind(SessionInterface::class, function (Application $app): SessionInterface {
            return $app->make(DriverInterface::class)->createSession();
        });

        $this->app->bind(TransactionInterface::class, function (Application $app): TransactionInterface {
            return $app->make(SessionInterface::class)->beginTransaction();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/neo4j.php' => config_path('neo4j.php'),
            ], 'neo4j-config');
        }
    }

    /**
     * @return (mixed|null|string)[]
     *
     * @psalm-return array{scheme: 'basic'|mixed, username: mixed|null, password: mixed|null, token?: mixed}
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
     * @return (array|int|mixed)[]
     *
     * @psalm-return array{connectionTimeout: 30|mixed, maxPoolSize: 100|mixed, ssl: array}
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
