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

/** @psalm-suppress UnusedClass */
final class Neo4jServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, function (Application $app): ClientInterface {
            $builder = ClientBuilder::create();
            $config = $app->make('config');

            $defaultConnection = $config->get('database.default');
            $connections = $config->get('database.connections');

            $configuredDrivers = [];

            foreach ($connections as $name => $connection) {
                if (! isset($connection['driver']) || $connection['driver'] !== 'neo4j') {
                    continue;
                }

                /** @psalm-suppress PossiblyInvalidArgument */
                $this->validateConnection($name, $connection);

                $url = $connection['url'] ?? sprintf(
                    'bolt://%s:%s',
                    $connection['host'] ?? 'localhost',
                    $connection['port'] ?? 7687
                );

                /** @psalm-suppress PossiblyInvalidArgument */
                $auth = $this->buildAuthentication($connection);

                /**
                 * @var array $connection
                 * @psalm-suppress UnnecessaryVarAnnotation
                 */
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

            if (! in_array($defaultConnection, $configuredDrivers, true)) {
                throw new BindingResolutionException('No valid Neo4j connection configured');
            }

            return $builder->build();
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

    private function buildAuthentication(array $config): \Laudis\Neo4j\Contracts\AuthenticateInterface
    {
        $scheme = $config['auth_scheme'] ?? 'basic';

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
        $driverConfig = null;

        if (
            isset($config['connection']['max_pool_size']) ||
            isset($config['connection']['timeout']) ||
            isset($config['ssl'])
        ) {
            $driverConfig = DriverConfiguration::default();

            if (isset($config['connection']['max_pool_size'])) {
                $driverConfig = $driverConfig->withMaxPoolSize($config['connection']['max_pool_size']);
            }

            if (isset($config['connection']['timeout'])) {
                $driverConfig = $driverConfig->withAcquireConnectionTimeout($config['connection']['timeout']);
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
        }

        return $driverConfig;
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
