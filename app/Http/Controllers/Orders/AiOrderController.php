<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiOrderController extends Controller
{
    public function __construct(
        private readonly AiOrderService $aiOrderService,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_table_id' => ['required', 'integer', 'exists:store_tables,id'],
            'order_text' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $userId = (int) ($request->user()?->id ?? 0);
        $tableId = (int) $validated['store_table_id'];
        $promptText = (string) $validated['order_text'];

        $request->session()->put($this->getAiPromptDraftSessionKey($userId, $tableId), $promptText);
        $request->session()->put($this->getAiPromptLastSessionKey($userId, $tableId), $promptText);

        $result = $this->aiOrderService->addFromText(
            $tableId,
            $promptText,
            $request->user()?->id
        );

        if (($result['added'] ?? 0) === 0) {
            return back()
                ->withInput()
                ->with('error', (string) ($result['message'] ?? 'Не успях да разпозная продукти от текста.'));
        }

        $request->session()->forget($this->getAiPromptDraftSessionKey($userId, $tableId));

        return back()->with('status', (string) ($result['message'] ?? 'AI добави артикули към поръчката.'));
    }

    private function getAiPromptDraftSessionKey(int $userId, int $tableId): string
    {
        return "ai_prompt_draft.user_{$userId}.table_{$tableId}";
    }

    private function getAiPromptLastSessionKey(int $userId, int $tableId): string
    {
        return "ai_prompt_last.user_{$userId}.table_{$tableId}";
    }
}

