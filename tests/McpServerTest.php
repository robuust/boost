<?php

namespace Robuust\Boost\Tests;

/**
 * @internal
 */
final class McpServerTest extends TestCase
{
    public function testAdvertisesProtocol20250618(): void
    {
        foreach (['2025-06-18', '2025-11-25', '2024-11-05'] as $requested) {
            $responses = $this->exchange([$this->initialize($requested)]);

            self::assertSame('2025-06-18', $responses[1]->result->protocolVersion, "client asked for {$requested}");
            self::assertSame('robuust', $responses[1]->result->serverInfo->name);
            self::assertObjectHasProperty('tools', $responses[1]->result->capabilities);
        }
    }

    public function testListsTheDocsTools(): void
    {
        $responses = $this->exchange([
            $this->initialize(),
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ]);

        $names = array_map(static fn (object $tool): string => $tool->name, $responses[2]->result->tools);
        sort($names);

        self::assertSame(['search-craft-docs', 'search-tailwind-docs'], $names);
    }

    public function testToolResultsCarryObjectStructuredContent(): void
    {
        $responses = $this->exchange([
            $this->initialize(),
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'search-craft-docs', 'arguments' => ['query' => 'cache tag']]],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'search-craft-docs', 'arguments' => ['query' => 'cache tag', 'major' => 6]]],
            ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'search-craft-docs', 'arguments' => ['query' => '   ']]],
        ]);

        $found = $responses[2]->result;
        self::assertInstanceOf(\stdClass::class, $found->structuredContent);
        self::assertFalse($found->isError ?? false);
        self::assertSame('5', $found->structuredContent->requested_major);
        self::assertSame('5', $found->structuredContent->served_major);
        self::assertSame('docs/5.x/reference/twig/tags.md', $found->structuredContent->results[0]->path);
        self::assertSame('text', $found->content[0]->type);
        self::assertEquals($found->structuredContent, json_decode($found->content[0]->text));

        $missing = $responses[3]->result;
        self::assertInstanceOf(\stdClass::class, $missing->structuredContent);
        self::assertNull($missing->structuredContent->served_major);
        self::assertSame([], $missing->structuredContent->results);

        $error = $responses[4]->result;
        self::assertTrue($error->isError);
        self::assertInstanceOf(\stdClass::class, $error->structuredContent);
        self::assertSame('query is required.', $error->structuredContent->error);
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(string $protocolVersion = '2025-06-18'): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => $protocolVersion,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ];
    }

    /**
     * Send messages to `robuust-agents mcp` and collect the responses by id.
     *
     * @param list<array<string, mixed>> $messages
     *
     * @return array<int, \stdClass>
     */
    private function exchange(array $messages): array
    {
        $root = $this->project('craft5');
        $input = implode("\n", array_map(static fn (array $message): string => (string) json_encode($message), $messages))."\n";

        $process = proc_open(
            [PHP_BINARY, self::packageRoot().'/bin/robuust-agents', 'mcp', '--root='.$root],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            array_merge(getenv(), ['ROBUUST_DOCS_BASE_URL' => 'file://'.self::fixture('docs')]),
        );
        self::assertIsResource($process);

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $responses = [];
        foreach (array_filter(explode("\n", $output)) as $line) {
            $message = json_decode($line);
            self::assertInstanceOf(\stdClass::class, $message, "Invalid output line: {$line}\n{$errors}");
            if (isset($message->id)) {
                $responses[(int) $message->id] = $message;
            }
        }

        self::assertCount(\count(array_filter($messages, static fn (array $message): bool => isset($message['id']))), $responses, $output.$errors);

        return $responses;
    }
}
