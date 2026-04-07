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

        $result = $this->authorizationService->authorize(
            $installation,
            $request->input('action'),
            $request->integer('quantity', 1),
        );

        AuditLog::create([
            'installation_id' => $installation->id,
            'action' => $request->input('action'),
            'result' => $result['allowed'] ? 'allowed' : 'denied',
            'request_data' => $request->validated(),
            'response_data' => $result,
            'ip_address' => $request->ip(),
        ]);

        return response()->json($result, $result['allowed'] ? 200 : 403);
    }
}
