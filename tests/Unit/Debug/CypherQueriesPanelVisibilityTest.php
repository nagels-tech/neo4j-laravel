<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\LaravelDebugbar;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Mockery;
use Neo4j\Neo4jLaravel\Debug\DebugbarAvailability;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;
use ReflectionMethod;

/**
 * End-to-end verification that the Cypher Queries panel is registered with
 * Laravel Debugbar's JavascriptRenderer and receives captured queries.
 */
class CypherQueriesPanelVisibilityTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            Neo4jServiceProvider::class,
            DebugbarServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.debug', true);
        $app['config']->set('debugbar.enabled', true);
        $app['config']->set('debugbar.collectors.neo4j', true);
        $app['config']->set('debugbar.options.neo4j.enabled', true);
        $app['config']->set('database.default', 'neo4j');
        $app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'x',
            'database' => 'neo4j',
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/__neo4j_debugbar_probe', function () {
            $summary = null;
            $result = new SummarizedResult($summary);

            $client = Mockery::mock(ClientInterface::class);
            $client->shouldReceive('readTransaction')->once()->andReturn($result);
            app()->instance(ClientInterface::class, $client);

            app('db')->connection('neo4j')->select(
                'MATCH (n:PanelProbe) RETURN n LIMIT $limit',
                ['limit' => 1]
            );

            return response('<html><head><title>probe</title></head><body>ok</body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        });
    }

    public function test_cypher_queries_panel_is_registered_in_debugbar_renderer(): void
    {
        $this->assertTrue(DebugbarAvailability::shouldCapture($this->app));

        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $debugbar->enable();

        $this->assertTrue($debugbar->hasCollector('neo4j'));
        $this->assertInstanceOf(Neo4jQueryCollector::class, $debugbar->getCollector('neo4j'));

        $js = $this->debugbarControlsJs($debugbar);

        $this->assertStringContainsString('addTab("neo4j"', $js);
        $this->assertStringContainsString('Cypher Queries', $js);
        $this->assertStringContainsString('PhpDebugBar.Widgets.LaravelQueriesWidget', $js);
        $this->assertStringContainsString('neo4j.nb_statements', $js);
        $this->assertStringContainsString('neo4j.tooltip', $js);

        $collector = $this->app->make(Neo4jQueryCollector::class);
        $assets = $collector->getAssets();
        $resources = dirname((new \ReflectionClass(\DebugBar\JavascriptRenderer::class))->getFileName()) . '/Resources';
        $this->assertFileExists($resources . '/' . $assets['css']);
        $this->assertFileExists($resources . '/' . $assets['js']);
    }

    public function test_collector_receives_cypher_query_and_dataset_includes_neo4j(): void
    {
        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $debugbar->enable();

        $summary = null;
        $result = new SummarizedResult($summary);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('readTransaction')->once()->andReturn($result);
        $this->app->instance(ClientInterface::class, $client);

        $cypher = 'MATCH (n:PanelProbe) RETURN n LIMIT $limit';
        $this->app->make('db')->connection('neo4j')->select($cypher, ['limit' => 1]);

        /** @var Neo4jQueryCollector $collector */
        $collector = $this->app->make(Neo4jQueryCollector::class);
        $collected = $collector->collect();

        $this->assertSame(1, $collected['nb_statements']);
        $this->assertSame($cypher, $collected['statements'][0]['sql']);
        $this->assertSame(['limit' => 1], (array) $collected['statements'][0]['params']);

        // Same collector instance as on Debugbar — dataset used by the panel.
        $dataset = $debugbar->getData();
        $this->assertArrayHasKey('neo4j', $dataset);
        $this->assertSame(1, $dataset['neo4j']['nb_statements']);
        $this->assertSame($cypher, $dataset['neo4j']['statements'][0]['sql']);
    }

    public function test_http_response_includes_debugbar_with_cypher_tab_and_query_data(): void
    {
        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $debugbar->enable();

        $response = $this->get('/__neo4j_debugbar_probe');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('phpdebugbar', $html);
        $this->assertStringContainsString('Cypher Queries', $html);
        $this->assertStringContainsString('addTab("neo4j"', $html);
        $this->assertStringContainsString('PhpDebugBar.Widgets.LaravelQueriesWidget', $html);

        /** @var Neo4jQueryCollector $collector */
        $collector = $this->app->make(Neo4jQueryCollector::class);
        $this->assertSame(1, $collector->getQueryCount());
        $this->assertSame(
            'MATCH (n:PanelProbe) RETURN n LIMIT $limit',
            $collector->collect()['statements'][0]['sql']
        );
    }

    private function debugbarControlsJs(LaravelDebugbar $debugbar): string
    {
        $renderer = $debugbar->getJavascriptRenderer();
        $method = new ReflectionMethod($renderer, 'getJsControlsDefinitionCode');
        $method->setAccessible(true);

        return (string) $method->invoke($renderer, 'phpdebugbar');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
