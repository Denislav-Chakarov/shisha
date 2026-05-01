<?php

namespace App\Services\Ai;

use App\Models\Product;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiOrderService
{
    public function __construct(
        private readonly AiOrderParser $parser,
        private readonly ProductMatcher $matcher,
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * @return array{added:int, message:string}
     */
    public function addFromText(int $storeTableId, string $orderText, ?int $userId = null): array
    {
        $products = Product::query()
            ->with(['brand:id,name', 'category:id,slug,behavior_type'])
            ->active()
            ->select(['id', 'brand_id', 'category_id', 'name', 'flavor', 'is_active'])
            ->get()
            ->map(function (Product $product): array {
                $brandName = (string) ($product->brand?->name ?? '');
                $search = trim($brandName . ' ' . $product->name . ' ' . ($product->flavor ?? ''));

                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'brand_name' => $brandName,
                    'behavior' => (string) ($product->category?->behavior_type ?? 'generic'),
                    'search_text' => $this->parser->normalizeOrderToken($search),
                ];
            })
            ->values()
            ->all();

        $items = $this->parser->parse($orderText);
        if ($items === []) {
            return ['added' => 0, 'message' => 'Не успях да разпозная продукти от текста.'];
        }

        $added = 0;

        DB::transaction(function () use ($items, $products, $storeTableId, $userId, &$added): void {
            $planned = [];
            $missed = [];

            $tobaccoCandidates = [];
            foreach ($products as $p) {
                if (($p['behavior'] ?? '') === 'tobacco') {
                    $tobaccoCandidates[] = $this->parser->normalizeOrderToken((string) $p['name']);
                }
            }

            foreach ($items as $item) {
                $term = (string) ($item['term'] ?? '');
                $quantity = (int) ($item['quantity'] ?? 1);
                if ($term === '' || $quantity < 1) {
                    continue;
                }

                $termLower = $this->parser->normalizeOrderToken($term);
                $match = $this->matcher->findBestMatch($termLower, $products);

                if ($match === null && $this->parser->isHookahKeyword($termLower)) {
                    foreach ($products as $p) {
                        if (($p['behavior'] ?? '') === 'hookah') {
                            $match = $p;
                            break;
                        }
                    }
                }

                if ($match === null) {
                    $missed[] = $term;
                    continue;
                }

                $metaNote = null;
                if (! empty($item['is_hookah'])) {
                    $hookahType = trim((string) ($item['hookah_type'] ?? ''));
                    $hookahFlavors = trim((string) ($item['hookah_flavors'] ?? ''));

                    $hookahProducts = array_values(array_filter($products, fn ($p) => ($p['behavior'] ?? '') === 'hookah'));

                    if ($hookahType !== '') {
                        $hookahTypeNormalized = $this->parser->normalizeHookahTypeToken($hookahType);

                        $typedHookahMatch = null;
                        $bestLen = 0;
                        foreach ($hookahProducts as $hp) {
                            $productName = $this->parser->normalizeOrderToken((string) ($hp['name'] ?? ''));
                            $brandName = $this->parser->normalizeOrderToken((string) ($hp['brand_name'] ?? ''));
                            if (str_contains($productName, $hookahTypeNormalized)
                                || str_contains($hookahTypeNormalized, $productName)
                                || str_contains($brandName, $hookahTypeNormalized)
                                || str_contains($hookahTypeNormalized, $brandName)
                            ) {
                                $len = mb_strlen((string) ($hp['name'] ?? ''));
                                if ($len > $bestLen) {
                                    $typedHookahMatch = $hp;
                                    $bestLen = $len;
                                }
                            }
                        }

                        if ($typedHookahMatch !== null) {
                            $match = $typedHookahMatch;

                            if ($hookahFlavors === '') {
                                $matchedBrandToken = $this->parser->normalizeHookahTypeToken((string) ($typedHookahMatch['brand_name'] ?? ''));
                                $matchedNameToken = $this->parser->normalizeHookahTypeToken((string) ($typedHookahMatch['name'] ?? ''));
                                $remainingFlavor = str_replace([$matchedBrandToken, $matchedNameToken], ' ', $hookahTypeNormalized);
                                $remainingFlavor = $this->parser->normalizeOrderToken($remainingFlavor);
                                if ($remainingFlavor !== '' && $remainingFlavor !== 'наргиле') {
                                    $hookahFlavors = $remainingFlavor;
                                }
                            }
                        } elseif (($match['behavior'] ?? '') !== 'hookah') {
                            $looksLikeFlavor = false;
                            foreach ($tobaccoCandidates as $candidateName) {
                                if ($candidateName !== '' && (str_contains($candidateName, $hookahTypeNormalized) || str_contains($hookahTypeNormalized, $candidateName))) {
                                    $looksLikeFlavor = true;
                                    break;
                                }
                            }

                            if (! $looksLikeFlavor) {
                                $missed[] = "тип наргиле: {$hookahType}";
                                continue;
                            }

                            if ($hookahFlavors === '') {
                                $hookahFlavors = $this->parser->normalizeOrderToken($hookahType);
                            }

                            if ($hookahProducts !== []) {
                                $match = $hookahProducts[0];
                            }
                        }
                    } elseif (($match['behavior'] ?? '') !== 'hookah') {
                        if ($hookahProducts !== []) {
                            $match = $hookahProducts[0];
                        }
                    }

                    if ($hookahFlavors !== '') {
                        $resolved = $this->parser->resolveHookahFlavors($hookahFlavors, $tobaccoCandidates);
                        $hookahFlavors = $resolved['value'];
                        if ($resolved['unknown'] !== []) {
                            $missed[] = 'вкус: ' . implode(' + ', $resolved['unknown']);
                            continue;
                        }
                    }

                    if (($match['behavior'] ?? '') !== 'hookah') {
                        $missed[] = $term;
                        continue;
                    }

                    if ($hookahFlavors !== '') {
                        $metaNote = mb_strtolower($hookahFlavors);
                    }
                }

                $productId = (int) ($match['id'] ?? 0);
                if ($productId < 1) {
                    $missed[] = $term;
                    continue;
                }

                $plannedKey = $productId . '|' . ($metaNote ?? '');
                if (! isset($planned[$plannedKey])) {
                    $planned[$plannedKey] = [
                        'product_id' => $productId,
                        'product_name' => (string) ($match['name'] ?? ''),
                        'quantity' => 0,
                        'meta_note' => $metaNote,
                    ];
                }
                $planned[$plannedKey]['quantity'] += $quantity;
            }

            if ($missed !== []) {
                throw ValidationException::withMessages([
                    'order_text' => 'Неразпознати продукти: ' . implode('; ', array_slice($missed, 0, 6)),
                ]);
            }

            foreach ($planned as $productPlan) {
                $this->orderService->appendItem(
                    $storeTableId,
                    (int) $productPlan['product_id'],
                    (int) $productPlan['quantity'],
                    $userId,
                    $productPlan['meta_note']
                );
                $added++;
            }
        });

        if ($added === 0) {
            return ['added' => 0, 'message' => 'Не успях да разпозная продукти от текста.'];
        }

        return ['added' => $added, 'message' => "AI добави {$added} артикула към поръчката."];
    }
}

