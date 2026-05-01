<?php

namespace App\Services\Ai;

class ProductMatcher
{
    public function __construct(
        private readonly AiOrderParser $parser,
    ) {
    }

    /**
     * @param array<int, array{id:int, name:string, brand_name:string, category:string, search_text:string}> $products
     * @return array{id:int, name:string, brand_name:string, category:string, search_text:string}|null
     */
    public function findBestMatch(string $termLower, array $products): ?array
    {
        $direct = null;
        $directLen = 0;
        foreach ($products as $product) {
            $productName = $this->parser->normalizeOrderToken((string) ($product['name'] ?? ''));
            $searchText = (string) ($product['search_text'] ?? '');

            if (str_contains($searchText, $termLower)
                || str_contains($termLower, $productName)
                || str_contains($productName, $termLower)
            ) {
                $len = mb_strlen($searchText);
                if ($len > $directLen) {
                    $direct = $product;
                    $directLen = $len;
                }
            }
        }

        if ($direct !== null) {
            return $direct;
        }

        $queryTokens = $this->extractMatchTokens($termLower);
        if ($queryTokens === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($products as $product) {
            $productTokens = $this->extractMatchTokens((string) ($product['search_text'] ?? ''));
            if ($productTokens === []) {
                continue;
            }

            $common = array_intersect($queryTokens, $productTokens);
            if ($common === []) {
                continue;
            }

            $score = count($common) / max(1, count($queryTokens));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $product;
            }
        }

        return $bestScore >= 0.5 ? $best : null;
    }

    /**
     * @return array<int, string>
     */
    private function extractMatchTokens(string $text): array
    {
        $normalized = $this->parser->normalizeOrderToken($text);
        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token === '') {
                continue;
            }

            if (in_array($token, ['бр', 'броя', 'брой', 'pc', 'pcs', 'x', 'х', 'и', 'and', 'с', 'със', 'with'], true)) {
                continue;
            }

            if (preg_match('/^\d+$/', $token) === 1) {
                continue;
            }

            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }
}

