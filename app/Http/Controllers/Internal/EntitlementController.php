<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntitlementController extends Controller
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $installation = $request->attributes->get('installation');

        $result = $this->authorizationService->getEntitlements($installation);

        return response()->json($result);
    }
}
