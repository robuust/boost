<?php

namespace Robuust\Boost\Docs;

/**
 * Minimal GET client. Supports `https://` and, for local indexes and tests, `file://` URLs.
 */
class HttpClient
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     */
    public function get(string $url, array $headers = []): array
    {
        if (str_starts_with($url, 'file://')) {
            $path = rawurldecode(substr($url, \strlen('file://')));

            return is_file($path)
                ? ['status' => 200, 'body' => (string) file_get_contents($path)]
                : ['status' => 404, 'body' => ''];
        }

        $headerLines = ['User-Agent: robuust-boost'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines),
                'ignore_errors' => true,
                'timeout' => 20,
            ],
        ]);

        $stream = @fopen($url, 'r', false, $context);

        if (false === $stream) {
            throw new \RuntimeException(\sprintf('Request failed: %s', $url));
        }

        try {
            $body = (string) stream_get_contents($stream);
            $responseHeaders = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        } finally {
            fclose($stream);
        }

        $status = 0;
        foreach (\is_array($responseHeaders) ? $responseHeaders : [] as $line) {
            if (\is_string($line) && 1 === preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return ['status' => $status, 'body' => $body];
    }
}
