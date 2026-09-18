<?php

namespace App\Http\Controllers\Api\TenantBot;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Prescription;
use App\Services\Ai\AiDataSharingPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Konteks data untuk model AI (MCP) beserta gerbang D-50(f).
 *
 * show() adalah satu-satunya jalur yang menyerahkan data entity ke bot. Data
 * kapabilitas sensitif hanya ikut bila owner sudah opt-in eksplisit; bila
 * tidak, entity-nya tidak dirakit sama sekali (bukan dikosongkan setelah
 * dimuat) dan bot menerima alasan yang bisa ia bacakan.
 */
class AiContextController extends Controller
{
    /**
     * Peta entity -> model untuk merakit payload. Dipisah dari
     * AiDataSharingPolicy karena ini urusan pengiriman, bukan kebijakan.
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'prescriptions' => Prescription::class,
    ];

    private const MAX_ROWS = 20;

    public function show(Request $request, AiDataSharingPolicy $policy): JsonResponse
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
        ]);

        $company = Company::findOrFail($payload['company_id']);

        $data = [];
        $withheld = [];

        foreach ($policy->sensitiveCapabilities() as $capability) {
            $entity = $policy->entityFor($capability);
            if ($entity === null || ! isset(self::MODELS[$entity])) {
                continue;
            }

            if (! $policy->allowsSharing($company, $capability)) {
                $withheld[] = [
                    'capability' => $capability,
                    'entity' => $entity,
                    'withheld_fields' => $policy->withheldFields($capability),
                    'message' => $policy->withheldReason($capability),
                ];

                continue;
            }

            $data[$entity] = $this->rowsFor($entity, $company, $policy->withheldFields($capability));
        }

        return response()->json([
            'company_id' => $company->id,
            'ai_data_sharing' => $policy->optIn($company),
            'data' => $data,
            'withheld' => $withheld,
        ]);
    }

    public function update(Request $request, AiDataSharingPolicy $policy): JsonResponse
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
            'ai_data_sharing' => 'required|array',
        ]);

        $company = Company::findOrFail($payload['company_id']);

        try {
            $optIn = $policy->normalizeOptIn($payload['ai_data_sharing']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        $policy->store($company, $optIn);

        return response()->json([
            'status' => 'success',
            'ai_data_sharing' => $policy->optIn($company->refresh()),
        ]);
    }

    /**
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private function rowsFor(string $entity, Company $company, array $fields): array
    {
        $model = self::MODELS[$entity];

        return $model::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(function ($row): array {
                return $row->toArray();
            })
            ->all();
    }
}
