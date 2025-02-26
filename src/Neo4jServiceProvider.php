<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
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

        $this->app->singleton(ClientInterface::class, function (Application $app): ClientInterface {
            $builder = ClientBuilder::create();
            $config = $app['config'];

            $defaultConnection = $config->get('database.default');
            $connections = $config->get('database.connections');

            $configuredDrivers = [];

            foreach ($connections as $name => $connection) {
                if (!isset($connection['driver']) || $connection['driver'] !== 'neo4j') {
                    continue;
                }

                $this->validateConnection($name, $connection);

                $url = $connection['url'] ?? sprintf(
                    'bolt://%s:%s',
                    $connection['host'] ?? 'localhost',
                    $connection['port'] ?? 7687
                );

                $auth = $this->buildAuthentication($connection);

                $driverConfig = $this->buildDriverConfiguration($connection);
                if ($driverConfig !== null) {
                    $builder = $builder->withDefaultDriverConfiguration($driverConfig);
                }

                $sessionConfig = SessionConfiguration::default();
                if (isset($connection['database'])) {
                    $sessionConfig = $sessionConfig->withDatabase($connection['database']);
                }
                $builder = $builder->withDefaultSessionConfiguration($sessionConfig);

                $builder = $builder->withDriver($name, $url, $auth);
                $configuredDrivers[] = $name;

                if ($name === $defaultConnection) {
                    $builder = $builder->withDefaultDriver($name);
                }
            }

            if (!in_array($defaultConnection, $configuredDrivers, true)) {
                throw new BindingResolutionException('No valid Neo4j connection configured');
            }

            return $builder->build();
        });

        $this->app->singleton(DriverInterface::class, function (Application $app): DriverInterface {
            return $app->make(ClientInterface::class)->getDriver(
                $app['config']->get('database.default')
            );
        });

        $this->app->bind(SessionInterface::class, function (Application $app): SessionInterface {
            return $app->make(DriverInterface::class)->createSession();
        });

        $this->app->bind(TransactionInterface::class, function (Application $app): TransactionInterface {
            return $app->make(SessionInterface::class)->beginTransaction();
        });

        Connection::resolverFor('neo4j', function ($connection, $database, $prefix, $config) {
            return new Neo4jConnection(
                $this->app->make(ClientInterface::class),
                $database,
                $prefix,
                $config
            );
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

    private function buildAuthentication(array $config): \Laudis\Neo4j\Contracts\AuthenticateInterface
    {
        $scheme = $config['scheme'] ?? 'basic';

        return match ($scheme) {
            'basic' => Authenticate::basic(
                $config['username'] ?? '',
                $config['password'] ?? ''
            ),
            'kerberos' => Authenticate::kerberos($config['ticket']),
            'oidc' => Authenticate::oidc($config['auth_token'] ?? $config['token'] ?? ''),
            'none' => Authenticate::disabled(),
            default => throw new BindingResolutionException("Unsupported authentication scheme: {$scheme}"),
        };
    }

    private function buildDriverConfiguration(array $config): ?DriverConfiguration
    {
        $driverConfig = DriverConfiguration::default();

        if (
            $this->app->has(HttpClientInterface::class) &&
            $this->app->has(StreamFactoryInterface::class) &&
            $this->app->has(RequestFactoryInterface::class)
        ) {
            $bindings = new HttpPsrBindings(
                $this->app->make(HttpClientInterface::class),
                $this->app->make(StreamFactoryInterface::class),
                $this->app->make(RequestFactoryInterface::class)
            );
            $driverConfig = $driverConfig->withHttpPsrBindings($bindings);
        }

        if (isset($config['pool']['max_size'])) {
            $driverConfig = $driverConfig->withMaxPoolSize($config['pool']['max_size']);
        }

        if (isset($config['timeout']['connection'])) {
            $driverConfig = $driverConfig->withAcquireConnectionTimeout($config['timeout']['connection']);
        }

        if (isset($config['ssl'])) {
            $sslMode = match ($config['ssl']['mode'] ?? 'from_url') {
                'enable' => SslMode::ENABLE(),
                'enable_with_self_signed' => SslMode::ENABLE_WITH_SELF_SIGNED(),
                'disable' => SslMode::DISABLE(),
                default => SslMode::FROM_URL(),
            };

            $sslConfig = SslConfiguration::create(
                $sslMode,
                $config['ssl']['verify_peer'] ?? true
            );

            $driverConfig = $driverConfig->withSslConfiguration($sslConfig);
        }

        return $driverConfig;
    }

    private function validateConnection(string $name, array $config): void
    {
        if (!isset($config['driver']) || $config['driver'] !== 'neo4j') {
            throw new BindingResolutionException("Invalid driver for Neo4j connection: {$name}");
        }

        if (!isset($config['url']) && (!isset($config['host']) || !isset($config['port']))) {
            throw new BindingResolutionException("Missing required URL or host/port configuration for Neo4j connection: {$name}");
        }
    }
}
