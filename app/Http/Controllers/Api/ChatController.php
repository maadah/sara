<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * ChatController — public API endpoint for the AI marketing chatbot.
 *
 * This is the sole HTTP entry point. It receives customer messages
 * and delegates everything to the ChatOrchestrator.
 *
 * Routes:
 *   POST /api/v1/chat               — process a message
 *   GET  /api/v1/chat/session/{id}   — session analytics
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ChatOrchestrator $orchestrator,
    ) {}

    /**
     * POST /api/v1/chat
     *
     * Accept a customer message and return the AI reply.
     *
     * Body (JSON):
     *   store_id        int      required
     *   lead_id         int      required
     *   message         string   required  (min 1, max 500)
     *   conversation_id string   optional  (social thread ID)
     *   channel         string   optional  'facebook'|'instagram'|'web'
     */
    public function processMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id'        => 'required|integer|exists:users,id',
            'lead_id'         => 'required|integer|exists:leads,id',
            'message'         => 'required|string|min:1|max:500',
            'conversation_id' => 'nullable|string|max:255',
            'channel'         => 'nullable|string|in:facebook,instagram,web',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $result = $this->orchestrator->processMessage(
                storeId:        $validated['store_id'],
                leadId:         $validated['lead_id'],
                message:        $validated['message'],
                conversationId: $validated['conversation_id'] ?? null,
                channel:        $validated['channel'] ?? 'web',
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('ChatController: unhandled error', [
                'store_id' => $validated['store_id'],
                'lead_id'  => $validated['lead_id'],
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'data'    => [
                    'reply'    => __('chat.error_general'),
                    'images'   => [],
                    'products' => [],
                    'actions'  => [],
                ],
            ], 500);
        }
    }

    /**
     * GET /api/v1/chat/session/{id}
     *
     * Return session analytics / statistics.
     */
    public function sessionStats(int $id): JsonResponse
    {
        try {
            $stats = $this->orchestrator->getSessionStats($id);

            return response()->json([
                'success' => true,
                'data'    => $stats,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Session not found.',
            ], 404);
        }
    }
}
