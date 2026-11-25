<?php

namespace Robuust\Boost\Mcp;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;
use Mcp\Server\Transport\StdioTransport;
use Robuust\Boost\Docs\DocsSearch;

/**
 * The `robuust` stdio MCP server for projects without Laravel Boost (Craft 5).
 *
 * Built on the official PHP MCP SDK, pinned to protocol 2025-06-18. Every tool
 * result carries `structuredContent` as a JSON object, also on errors.
 */
final class McpServer
{
    public const NAME = 'robuust';

    public const VERSION = '0.1.0';

    public const INSTRUCTIONS = 'Documentation search for this project. Use search-craft-docs for a concrete Craft CMS, Yii or Craft/Twig API question, and search-tailwind-docs for a concrete Tailwind CSS v4 question. Call neither when repository evidence is enough. Project rules are not served here: read .ai/rules/index.md as AGENTS.md describes.';

    public function __construct(private readonly DocsSearch $docs)
    {
    }

    public function builder(): Builder
    {
        $readOnly = new ToolAnnotations(readOnlyHint: true, openWorldHint: true);

        return (new Builder())
            ->setServerInfo(self::NAME, self::VERSION)
            ->setInstructions(self::INSTRUCTIONS)
            ->setProtocolVersion(ProtocolVersion::V2025_06_18)
            ->withoutModernEra()
            ->addTool(
                fn (string $query, int|string|null $major = null, int $max_results = 5): CallToolResult => $this->call(
                    fn (): array => $this->docs->craft($query, $major, $max_results),
                ),
                'search-craft-docs',
                'Search Craft CMS docs',
                DocsSearch::CRAFT_DESCRIPTION,
                $readOnly,
                DocsSearch::craftSchema(),
            )
            ->addTool(
                fn (string $query, int $max_results = 5): CallToolResult => $this->call(
                    fn (): array => $this->docs->tailwind($query, $max_results),
                ),
                'search-tailwind-docs',
                'Search Tailwind CSS docs',
                DocsSearch::TAILWIND_DESCRIPTION,
                $readOnly,
                DocsSearch::tailwindSchema(),
            );
    }

    /**
     * Serve over stdin and stdout until the client disconnects.
     */
    public function run(): void
    {
        $this->builder()->build()->run(new StdioTransport());
    }

    /**
     * Wrap a search in a tool result whose structured content is always an object.
     *
     * @param \Closure(): array<string, mixed> $search
     */
    public static function call(\Closure $search): CallToolResult
    {
        try {
            $result = $search();
            $isError = false;
        } catch (\Throwable $exception) {
            $result = ['error' => $exception->getMessage()];
            $isError = true;
        }

        $structured = (object) $result;
        $text = json_encode($structured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return new CallToolResult([new TextContent((string) $text)], $isError, $structured);
    }
}
