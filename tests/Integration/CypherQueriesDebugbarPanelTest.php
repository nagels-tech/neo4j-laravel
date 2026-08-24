<?php

namespace Neo4j\Neo4jLaravel\Tests\Integration;

use Barryvdh\Debugbar\LaravelDebugbar;
use Illuminate\Support\Facades\DB;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Tests\TestCase;
use ReflectionMethod;

/**
 * Live Neo4j + Laravel Debugbar: Cypher Queries panel registration, capture, and render controls.
 */
class CypherQueriesDebugbarPanelTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.debug', true);
        $app['config']->set('debugbar.enabled', true);
        $app['config']->set('debugbar.collectors.neo4j', true);
        $app['config']->set('debugbar.options.neo4j.enabled', true);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/__cypher_debugbar_live', function () {
            DB::connection('neo4j')->write(
                'CREATE (n:CypherPanelLive {name: $name}) RETURN n',
                ['name' => 'live-panel']
            );

            return response(
                '<html><head><title>cypher-panel</title></head><body>live</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $debugbar->enable();
        $this->app->make(Neo4jQueryCollector::class)->reset();
        DB::connection('neo4j')->enableQueryLog();
    }

    public function test_live_query_appears_in_cypher_panel_collector_and_debugbar_dataset(): void
    {
        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $this->assertTrue($debugbar->hasCollector('neo4j'));

        $cypher = 'CREATE (n:CypherPanelLive {name: $name}) RETURN n';
        DB::connection('neo4j')->write($cypher, ['name' => 'dataset']);

        /** @var Neo4jQueryCollector $collector */
        $collector = $this->app->make(Neo4jQueryCollector::class);
        $data = $collector->collect();

        $this->assertSame(1, $data['nb_statements']);
        $this->assertSame($cypher, $data['statements'][0]['sql']);
        $this->assertSame(['name' => 'dataset'], (array) $data['statements'][0]['params']);

        $dataset = $debugbar->getData();
        $this->assertArrayHasKey('neo4j', $dataset);
        $this->assertSame($cypher, $dataset['neo4j']['statements'][0]['sql']);

        $js = $this->controlsJs($debugbar);
        $this->assertStringContainsString('addTab("neo4j"', $js);
        $this->assertStringContainsString('Cypher Queries', $js);
        $this->assertStringContainsString('PhpDebugBar.Widgets.LaravelQueriesWidget', $js);
    }

    public function test_http_html_response_injects_debugbar_with_cypher_query_payload(): void
    {
        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');

        $response = $this->get('/__cypher_debugbar_live');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('phpdebugbar', $html);
        $this->assertStringContainsString('Cypher Queries', $html);
        $this->assertStringContainsString('addTab("neo4j"', $html);
        $this->assertStringContainsString('PhpDebugBar.Widgets.LaravelQueriesWidget', $html);
        $this->assertStringContainsString('CypherPanelLive', $html);

        /** @var Neo4jQueryCollector $collector */
        $collector = $this->app->make(Neo4jQueryCollector::class);
        $this->assertGreaterThanOrEqual(1, $collector->getQueryCount());
        $this->assertStringContainsString(
            'CREATE (n:CypherPanelLive',
            $collector->collect()['statements'][0]['sql']
        );

        // Inline dataset (when Debugbar embeds it) or open-handler storage should
        // still leave the collector populated for the rendered request.
        $dataset = $debugbar->getData();
        $this->assertArrayHasKey('neo4j', $dataset);
        $this->assertGreaterThanOrEqual(1, $dataset['neo4j']['nb_statements']);
    }

    private function controlsJs(LaravelDebugbar $debugbar): string
    {
        $method = new ReflectionMethod($debugbar->getJavascriptRenderer(), 'getJsControlsDefinitionCode');
        $method->setAccessible(true);

        return (string) $method->invoke($debugbar->getJavascriptRenderer(), 'phpdebugbar');
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('neo4j')->write('MATCH (n:CypherPanelLive) DELETE n');
        } catch (\Throwable) {
            // Ignore cleanup failures so test failures stay primary.
        }

        parent::tearDown();
    }
}
