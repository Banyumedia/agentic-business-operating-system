<?php

namespace App\Services\Workflow\Effects;

use App\Models\Company;
use App\Services\HermesNodeClient;
use RuntimeException;

class NotifyOwnerWa implements WorkflowEffect
{
    public function __construct(private readonly HermesNodeClient $hermesClient) {}

    public function key(): string
    {
        return 'notify.owner_wa';
    }

    public function execute(array $context): array
    {
        $company = Company::with('owner')->where('id', $context['company'])->first();

        if (! $company || ! $company->owner) {
            throw new RuntimeException("Cannot send WA notification: Owner not found for company {$context['company']}");
        }

        $owner = $company->owner;

        if (! $owner->wa_is_verified || empty($owner->wa_number)) {
            throw new RuntimeException('Cannot send WA notification: Owner WA number is not verified or empty.');
        }

        $message = "Transisi workflow: Entity {$context['entity']} ({$context['record_id']}) pindah ke stage '{$context['to']}' oleh role '{$context['actor_role']}'.";

        try {
            $this->hermesClient->sendWhatsAppMessage($company->id, $owner->wa_number, $message);
        } catch (RuntimeException $e) {
            throw new RuntimeException('WA Delivery failed: '.$e->getMessage(), 0, $e);
        }

        return [
            'effect' => $this->key(),
            'status' => 'sent',
            'wa_number' => $owner->wa_number,
        ];
    }
}
