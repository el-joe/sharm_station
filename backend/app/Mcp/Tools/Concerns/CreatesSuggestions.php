<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\AiSuggestion;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Shared by the write-as-suggestion tools: who created it and the standard "pending review" answer.
 */
trait CreatesSuggestions
{
    protected function creator(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    protected function token(Request $request): ?PersonalAccessToken
    {
        $token = $this->creator($request)?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    protected function pendingResponse(AiSuggestion $suggestion): ResponseFactory
    {
        return Response::structured([
            'suggestion_id' => $suggestion->id,
            'type' => $suggestion->type->value,
            'status' => $suggestion->status->value,
            'message' => 'Saved as a PENDING suggestion. Nothing has been published: an admin must review, edit and approve it in the back office (AI suggestions) before it is applied.',
            'review_url' => route('admin.ai-suggestions.show', $suggestion),
        ]);
    }
}
