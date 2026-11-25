<?php

namespace Robuust\Boost\Boost\Tools;

use Illuminate\Container\Container;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Robuust\Boost\Docs\CraftDocs;
use Robuust\Boost\Docs\DocsSearch;
use Robuust\Boost\Stack;

/**
 * Boost MCP tool `search-craft-docs`, registered on Craft 6 projects.
 */
#[IsReadOnly]
class SearchCraftDocs extends Tool
{
    protected string $description = DocsSearch::CRAFT_DESCRIPTION;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('A concrete query, such as a tag, method or setting name.')
                ->required(),
            'major' => $schema->integer()
                ->description('Craft major to search (5 or 6). Defaults to the project\'s installed major.'),
            'max_results' => $schema->integer()->min(1)->max(10)
                ->description('Maximum results to return. Defaults to 5.'),
        ];
    }

    /**
     * Return the result as an object in `structuredContent`, mirrored as JSON text, also on errors.
     */
    public function handle(Request $request): ResponseFactory
    {
        $configured = Container::getInstance()->make('config')->get('robuust-boost.docs_base_url');
        $docs = new DocsSearch(
            Stack::current(),
            new CraftDocs(CraftDocs::baseUrl(\is_string($configured) ? $configured : null)),
        );

        $major = $request->get('major');
        $maxResults = $request->get('max_results', 5);

        try {
            return Response::structured($docs->craft(
                (string) $request->get('query'),
                \is_int($major) || \is_string($major) ? $major : null,
                is_numeric($maxResults) ? (int) $maxResults : 5,
            ));
        } catch (\Throwable $exception) {
            $error = ['error' => $exception->getMessage()];

            return Response::make(Response::error((string) json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
                ->withStructuredContent($error);
        }
    }
}
