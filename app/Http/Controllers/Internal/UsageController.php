<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsageReportRequest;
use App\Models\AuditLog;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function __invoke(UsageReportRequest $request): JsonResponse
    {
        $installation = $request->attributes->get('installation');

        // Idempotency: if reference_id already processed, return cached response
        if ($request->input('reference_id')) {
            $existing = AuditLog::where('installation_id', $installation->id)
                ->where('reference_id', $request->input('reference_id'))
                ->first();

            if ($existing) {
                return response()->json($existing->response_data);
            }
        }

        $result = $this->authorizationService->reportUsage(
            $installation,
            $request->input('metric'),
            $request->integer('value', 1),
            $request->input('reference_id'),
        );

        AuditLog::create([
            'installation_id' => $installation->id,
            'action' => "usage:{$request->input('metric')}",
            'result' => 'recorded',
            'request_data' => $request->validated(),
            'response_data' => $result,
            'reference_id' => $request->input('reference_id'),
            'ip_address' => $request->ip(),
        ]);

        return response()->json($result);
    }
}
