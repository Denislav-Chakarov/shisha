<?php

namespace App\Services\Ai;

class AiOrderParser
{
    /**
     * @return array<int, array{term: string, quantity: int, is_hookah?: bool, hookah_type?: string, hookah_flavors?: string}>
     */
    public function parse(string $orderText): array
    {
        $preparedOrderText = preg_replace(
            '/\s+\bи\b\s+(?=(?:\d+|[[:alpha:]\p{L}]+\s+(?:бр|броя|брой)|наргиле|наргилета|hookah|shisha))/ui',
            ', ',
            $orderText
        );
        $segments = $this->splitOrderSegments((string) $preparedOrderText);

        $expandedSegments = [];
        foreach ($segments as $segment) {
            foreach ($this->expandCompositeOrderSegment((string) $segment) as $part) {
                $cleanPart = trim((string) $part);
                if ($cleanPart !== '') {
                    $expandedSegments[] = $cleanPart;
                }
            }
        }
        $segments = $expandedSegments;

        $items = [];
        foreach ($segments as $segment) {
            $raw = trim((string) $segment);
            if ($raw === '') {
                continue;
            }

            $quantity = 1;
            $term = $raw;

            if (preg_match('/^\s*(\d+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s+(.+)$/ui', $raw, $matches) === 1) {
                $quantity = max(1, (int) $matches[1]);
                $term = trim((string) $matches[2]);
            } elseif (preg_match('/^(.+?)\s+(\d+)\s*(?:x|х|бр|броя|брой|pcs|pc)\s*(.*)$/ui', $raw, $matches) === 1) {
                $quantity = max(1, (int) $matches[2]);
                $term = trim(trim((string) $matches[1]) . ' ' . trim((string) $matches[3]));
            } elseif (preg_match('/^\s*([[:alpha:]\p{L}]+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s+(.+)$/ui', $raw, $matches) === 1) {
                $wordNumber = $this->parseQuantityWord((string) $matches[1]);
                if ($wordNumber !== null) {
                    $quantity = $wordNumber;
                    $term = trim((string) $matches[2]);
                }
            } elseif (preg_match('/^(.+?)\s+(\d+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s*$/ui', $raw, $matches) === 1) {
                $term = trim((string) $matches[1]);
                $quantity = max(1, (int) $matches[2]);
            } elseif (preg_match('/^(.+?)\s*[xх]\s*(\d+)$/ui', $raw, $matches) === 1) {
                $term = trim((string) $matches[1]);
                $quantity = max(1, (int) $matches[2]);
            } elseif (preg_match('/^(.+?)\s+([[:alpha:]\p{L}]+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s*$/ui', $raw, $matches) === 1) {
                $wordNumber = $this->parseQuantityWord((string) $matches[2]);
                if ($wordNumber !== null) {
                    $term = trim((string) $matches[1]);
                    $quantity = $wordNumber;
                }
            } elseif (preg_match('/^(.+?)\s+([[:alpha:]\p{L}]+)\s+(?:бр|броя|брой|pcs|pc)\s*$/ui', $raw, $matches) === 1) {
                $wordNumber = $this->parseQuantityWord((string) $matches[2]);
                if ($wordNumber !== null) {
                    $term = trim((string) $matches[1]);
                    $quantity = $wordNumber;
                }
            }

            $isHookahSegment = $this->isHookahKeyword($this->normalizeOrderToken($term));
            $hookahFlavors = '';
            if ($isHookahSegment && preg_match('/\(([^)]+)\)/u', $term, $flavorMatches) === 1) {
                $flavorParts = preg_split('/\s*\+\s*|\s*,\s*|\s+\bи\b\s+|\s+\band\b\s+/ui', (string) $flavorMatches[1]) ?: [];
                $normalizedFlavors = [];
                foreach ($flavorParts as $flavorPart) {
                    $flavorNormalized = $this->normalizeOrderToken((string) $flavorPart);
                    if ($flavorNormalized !== '') {
                        $normalizedFlavors[] = $flavorNormalized;
                    }
                }
                $hookahFlavors = implode(' + ', $normalizedFlavors);
            }

            $termWithoutMeta = (string) preg_replace('/\([^)]*\)/u', ' ', $term);
            $termWithoutMeta = (string) preg_replace('/\b(чашка|чаша|глава|глави|head|bowl)\b/ui', ' ', $termWithoutMeta);

            $hookahType = '';
            if ($isHookahSegment) {
                $hookahTypeRaw = (string) preg_replace('/\b(наргиле|hookah|shisha|с|със|with)\b/ui', ' ', $termWithoutMeta);
                $hookahType = $this->normalizeOrderToken($hookahTypeRaw);

                if ($hookahFlavors === '' && preg_match('/\b(?:с|със|with)\b\s+(.+)$/ui', $term, $flavorFromTextMatch) === 1) {
                    $flavorParts = preg_split('/\s*\+\s*|\s*,\s*|\s+\bи\b\s+|\s+\band\b\s+/ui', (string) $flavorFromTextMatch[1]) ?: [];
                    $normalizedFlavors = [];
                    foreach ($flavorParts as $flavorPart) {
                        $flavorNormalized = $this->normalizeOrderToken((string) $flavorPart);
                        if ($flavorNormalized !== '' && $flavorNormalized !== $hookahType && $flavorNormalized !== 'наргиле') {
                            $normalizedFlavors[] = $flavorNormalized;
                        }
                    }
                    $hookahFlavors = implode(' + ', array_values(array_unique($normalizedFlavors)));
                }
            }

            $normalized = $this->normalizeOrderToken($termWithoutMeta);
            if ($normalized === '') {
                continue;
            }

            $entry = [
                'term' => $normalized,
                'quantity' => $quantity,
            ];
            if ($isHookahSegment) {
                $entry['is_hookah'] = true;
                $entry['hookah_type'] = $hookahType;
                $entry['hookah_flavors'] = $hookahFlavors;
            }
            $items[] = $entry;
        }

        return $items;
    }

    public function normalizeOrderToken(string $text): string
    {
        $value = mb_strtolower(trim($text));
        $value = str_replace(
            ['кока-кола', 'кока кола', 'coca cola', 'шиша', 'наргилета', 'фанта', 'мл'],
            ['coca cola', 'coca cola', 'coca cola', 'наргиле', 'наргиле', 'fanta', 'ml'],
            $value
        );
        $value = str_replace(
            ['рокетмен', 'блубери', 'блу бери'],
            ['rocketman', 'blueberry', 'blueberry'],
            $value
        );
        $value = (string) preg_replace('/\b(коли|кола|кока)\b/ui', 'coca cola', $value);
        $value = str_replace(
            [' със ', ' с ', ' with '],
            [' ', ' ', ' '],
            " {$value} "
        );
        $value = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        $value = (string) preg_replace('/\b(вкус|аромат|flavor|taste)\b/ui', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);
        return trim($value);
    }

    public function isHookahKeyword(string $text): bool
    {
        return str_contains($text, 'наргиле')
            || str_contains($text, 'hookah')
            || str_contains($text, 'shisha');
    }

    public function normalizeHookahTypeToken(string $text): string
    {
        $value = $this->normalizeOrderToken($text);
        return str_replace(
            ['карма', 'aeon', 'аеон', 'маклауд', 'механика'],
            ['karm', 'aoen', 'aoen', 'maklaud', 'mechanica'],
            $value
        );
    }

    /**
     * @param array<int, string> $tobaccoCandidateNamesNormalized
     * @return array{value: string, unknown: array<int, string>}
     */
    public function resolveHookahFlavors(string $flavors, array $tobaccoCandidateNamesNormalized): array
    {
        $parts = preg_split('/\s*\+\s*/u', $flavors) ?: [];
        if ($parts === []) {
            return ['value' => '', 'unknown' => []];
        }

        $candidates = array_values(array_unique(array_values(array_filter($tobaccoCandidateNamesNormalized))));
        if ($candidates === []) {
            $unknown = array_values(array_filter(array_map(fn ($p) => $this->normalizeOrderToken((string) $p), $parts)));
            return ['value' => $this->normalizeOrderToken($flavors), 'unknown' => $unknown];
        }

        $corrected = [];
        $unknown = [];

        foreach ($parts as $part) {
            $token = $this->normalizeOrderToken((string) $part);
            if ($token === '') {
                continue;
            }

            $best = null;
            $bestDistance = PHP_INT_MAX;
            $tokenCompact = str_replace(' ', '', $token);

            foreach ($candidates as $candidateString) {
                if ($candidateString === $token) {
                    $best = $candidateString;
                    $bestDistance = 0;
                    break;
                }

                if (str_contains($candidateString, $token) || str_contains($token, $candidateString)) {
                    $best = $candidateString;
                    $bestDistance = 0;
                    break;
                }

                $candidateCompact = str_replace(' ', '', $candidateString);
                $distance = levenshtein($tokenCompact, $candidateCompact);
                $threshold = max(1, (int) floor(strlen($candidateCompact) * 0.35));

                if ($distance <= $threshold && $distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $candidateString;
                }
            }

            if ($best === null) {
                $unknown[] = $token;
                continue;
            }

            $corrected[] = $best;
        }

        return [
            'value' => implode(' + ', array_values(array_unique($corrected))),
            'unknown' => array_values(array_unique($unknown)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function expandCompositeOrderSegment(string $segment): array
    {
        $clean = trim($segment);
        if ($clean === '') {
            return [];
        }

        $parts = preg_split(
            '/\s+\bс\b\s+(?=(?:\d+|[[:alpha:]\p{L}]+)\s*(?:бр|броя|брой)?\s+)/ui',
            $clean
        ) ?: [];

        $result = [];
        foreach ($parts as $part) {
            $value = trim((string) $part);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return $result === [] ? [$clean] : $result;
    }

    private function parseQuantityWord(string $value): ?int
    {
        $normalized = $this->normalizeOrderToken($value);
        $map = [
            '1' => 1,
            'един' => 1,
            'една' => 1,
            'едно' => 1,
            'one' => 1,
            '2' => 2,
            'два' => 2,
            'две' => 2,
            'two' => 2,
            '3' => 3,
            'три' => 3,
            'three' => 3,
            '4' => 4,
            'четири' => 4,
            'four' => 4,
            '5' => 5,
            'пет' => 5,
            'five' => 5,
            '6' => 6,
            'шест' => 6,
            'six' => 6,
            '7' => 7,
            'седем' => 7,
            'seven' => 7,
            '8' => 8,
            'осем' => 8,
            'eight' => 8,
            '9' => 9,
            'девет' => 9,
            'nine' => 9,
            '10' => 10,
            'десет' => 10,
            'ten' => 10,
        ];

        return $map[$normalized] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function splitOrderSegments(string $text): array
    {
        $segments = [];
        $buffer = '';
        $depth = 0;

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }

            $isPlusSeparator = $char === '+' && $depth === 0;
            if ($isPlusSeparator) {
                $normalizedBuffer = $this->normalizeOrderToken($buffer);
                if ($this->isHookahKeyword($normalizedBuffer)) {
                    $buffer .= ' ' . $char . ' ';
                    continue;
                }
            }

            $isSeparator = ($char === '+' || $char === ',' || $char === ';' || $char === "\n" || $char === "\r") && $depth === 0;
            if ($isSeparator) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $segments[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $segments[] = $trimmed;
        }

        return $segments;
    }
}

