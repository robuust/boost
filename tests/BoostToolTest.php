<?php

namespace Robuust\Boost\Tests;

use Illuminate\Container\Container;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\ToolInvoker;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Robuust\Boost\Boost\Tools\SearchCraftDocs;
use Robuust\Boost\Stack;

/**
 * The Boost MCP tool, serialized the way laravel/mcp answers `tools/call` (D10).
 *
 * @internal
 */
final class BoostToolTest extends TestCase
{
    private ?Container $previous = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previous = Container::getInstance();
        $container = new Container();
        $container->instance('config', new class {
            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }
        });
        Container::setInstance($container);

        putenv('ROBUUST_DOCS_BASE_URL=file://'.self::fixture('docs'));
        Stack::useRoot($this->project('craft5'));
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previous);

        parent::tearDown();
    }

    public function testResultCarriesObjectStructuredContent(): void
    {
        $result = $this->call(['query' => 'cache tag']);

        self::assertFalse($result->isError);
        self::assertInstanceOf(\stdClass::class, $result->structuredContent);
        self::assertSame('5', $result->structuredContent->requested_major);
        self::assertSame('5', $result->structuredContent->served_major);
        self::assertSame('docs/5.x/reference/twig/tags.md', $result->structuredContent->results[0]->path);
        self::assertSame('text', $result->content[0]->type);
        self::assertEquals($result->structuredContent, json_decode($result->content[0]->text));

        $missing = $this->call(['query' => 'cache tag', 'major' => 6]);
        self::assertInstanceOf(\stdClass::class, $missing->structuredContent);
        self::assertNull($missing->structuredContent->served_major);
        self::assertSame([], $missing->structuredContent->results);
    }

    public function testErrorCarriesObjectStructuredContent(): void
    {
        $error = $this->call(['query' => '   ']);

        self::assertTrue($error->isError);
        self::assertInstanceOf(\stdClass::class, $error->structuredContent);
        self::assertSame('query is required.', $error->structuredContent->error);
        self::assertEquals($error->structuredContent, json_decode($error->content[0]->text));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function call(array $arguments): \stdClass
    {
        Container::getInstance()->instance(Request::class, new Request($arguments));

        $response = (new ToolInvoker())->invoke(
            new SearchCraftDocs(),
            new JsonRpcRequest(1, 'tools/call', ['name' => 'search-craft-docs', 'arguments' => $arguments]),
        );
        self::assertInstanceOf(JsonRpcResponse::class, $response);

        $decoded = json_decode($response->toJson());
        self::assertInstanceOf(\stdClass::class, $decoded);
        self::assertInstanceOf(\stdClass::class, $decoded->result);

        return $decoded->result;
    }
}
