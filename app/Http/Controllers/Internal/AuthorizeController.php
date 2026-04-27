<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthorizeRequest;
use App\Models\AuditLog;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;

class AuthorizeController extends Controller
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function __invoke(AuthorizeRequest $request): JsonResponse
    {
        $installation = $request->attributes->get('installation');

        $consume = (bool) $request->input('consume', false);
        $referenceId = $request->input('reference_id');

        // Idempotency: if consume + reference_id was already ALLOWED, return cached response.
        // Denied responses are NOT cached: the client should be able to retry after a limit increase.
        if ($consume && $referenceId) {
            $existing = AuditLog::where('installation_id', $installation->id)
                ->where('reference_id', $referenceId)
                ->where('result', 'allowed')
                ->first();

            if ($existing) {
                return response()->json($existing->response_data, 200);
            }
        }

        $result = $this->authorizationService->authorize(
            $installation,
            $request->input('action'),
            $request->integer('quantity', 1),
            $consume,
            $referenceId,
        );

        AuditLog::create([
            'installation_id' => $installation->id,
            'action' => $request->input('action'),
            'result' => $result['allowed'] ? 'allowed' : 'denied',
            'request_data' => $request->validated(),
            'response_data' => $result,
            'reference_id' => $consume ? $referenceId : null,
            'ip_address' => $request->ip(),
        ]);

        return response()->json($result, $result['allowed'] ? 200 : 403);
    }
}
