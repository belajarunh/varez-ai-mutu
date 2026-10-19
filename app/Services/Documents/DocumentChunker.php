<?php

namespace App\Services\Documents;

class DocumentChunker
{
    private const MAX_CHARS = 3000;

    private const OVERLAP_CHARS = 300;

    /**
     * @param  list<array{page: int|null, section: string|null, text: string}>  $pages
     * @return list<array{chunk_index: int, content_text: string, page_start: int|null, page_end: int|null, section_title: string|null, token_estimate: int, content_hash: string, metadata_json: array<string, mixed>}>
     */
    public function chunk(array $pages, string $parser): array
    {
        $chunks = [];
        foreach ($pages as $page) {
            $text = $this->normalize($page['text']);
            if ($text === '') {
                continue;
            }

            $offset = 0;
            $length = mb_strlen($text);
            while ($offset < $length) {
                $piece = mb_substr($text, $offset, self::MAX_CHARS);
                if ($offset + mb_strlen($piece) < $length) {
                    $boundary = mb_strrpos($piece, ' ');
                    if ($boundary !== false && $boundary > self::MAX_CHARS - 500) {
                        $piece = mb_substr($piece, 0, $boundary);
                    }
                }
                $piece = trim($piece);
                if ($piece !== '') {
                    $chunks[] = [
                        'chunk_index' => count($chunks),
                        'content_text' => $piece,
                        'page_start' => $page['page'],
                        'page_end' => $page['page'],
                        'section_title' => $page['section'],
                        'token_estimate' => max(1, (int) ceil(str_word_count($piece) * 1.3)),
                        'content_hash' => hash('sha256', $piece),
                        'metadata_json' => ['parser' => $parser],
                    ];
                }
                if (count($chunks) > 5000) {
                    throw new \RuntimeException('Document produced more than 5,000 chunks.');
                }

                $consumed = max(1, mb_strlen($piece));
                $offset += $offset + $consumed >= $length
                    ? $consumed
                    : max(1, $consumed - self::OVERLAP_CHARS);
            }
        }

        return $chunks;
    }

    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);
        $text = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $lines = preg_split('/\n+/u', $text) ?: [];
        $lines = array_map(fn (string $line): string => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? ''), $lines);
        $lines = array_filter($lines, fn (string $line): bool => $line !== '');

        return implode("\n", $lines);
    }
}
