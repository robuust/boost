<?php

namespace Robuust\Boost\Tests;

use Robuust\Boost\Docs\HttpClient;

final class FakeTailwindClient extends HttpClient
{
    public int $requests = 0;

    /** @var array<string, string> */
    private array $docs = [
        'src/docs/adding-custom-styles.mdx' => "export const title = \"Adding custom styles\";\n\nTailwind is designed to be extensible, so how it does its work never gets in your way.\n",
        'src/docs/functions-and-directives.mdx' => "export const title = \"Functions and directives\";\n\n## Directives\n\n### @utility\n\nUse the `@utility` directive to add custom utilities to your project that work with variants.\n\n### @apply\n\nUse the `@apply` directive to inline any existing utility classes into your own custom CSS.\n",
        'src/docs/upgrade-guide.mdx' => "export const title = \"Upgrade guide\";\n\nTailwind CSS v4 uses CSS-first configuration.\n\nReplace the `@tailwind` directives with a regular CSS `@import`.\n",
    ];

    public function get(string $url, array $headers = []): array
    {
        ++$this->requests;

        if (str_ends_with($url, '/commits/main')) {
            return ['status' => 200, 'body' => (string) json_encode(['sha' => '0123456789abcdef0123456789abcdef01234567'])];
        }

        if (str_contains($url, '/git/trees/')) {
            $tree = array_map(static fn (string $path): array => ['path' => $path, 'type' => 'blob'], array_keys($this->docs));

            return ['status' => 200, 'body' => (string) json_encode(['tree' => $tree])];
        }

        foreach ($this->docs as $path => $content) {
            if (str_ends_with($url, $path)) {
                return ['status' => 200, 'body' => $content];
            }
        }

        return ['status' => 404, 'body' => ''];
    }
}
