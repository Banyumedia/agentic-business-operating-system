<?php

namespace App\Services\Token;

use App\Models\AiModelPricing;
use App\Models\CompanyMembership;

class ModelSelector
{
    public function __construct(
        private readonly EmergencyModeResolver $emergencyResolver
    ) {}

    public function selectModelForInference(CompanyMembership $membership, ?string $preferredModel = null): ?AiModelPricing
    {
        if ($this->emergencyResolver->isDepleted($membership)) {
            return null; // Can't select a model if depleted
        }

        if ($this->emergencyResolver->isEmergencyModeActive($membership)) {
            return $this->getCheapestFallbackModel();
        }

        if ($preferredModel) {
            $model = AiModelPricing::where('model_name', $preferredModel)
                ->where('is_active', true)
                ->first();

            if ($model) {
                return $model;
            }
        }

        // Return a default active model
        return AiModelPricing::where('is_active', true)->orderBy('id')->first();
    }

    private function getCheapestFallbackModel(): ?AiModelPricing
    {
        return AiModelPricing::where('is_active', true)
            ->orderBy('input_multiplier', 'asc')
            ->orderBy('output_multiplier', 'asc')
            ->first();
    }
}
