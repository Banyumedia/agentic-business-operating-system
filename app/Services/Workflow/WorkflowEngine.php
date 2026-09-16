<?php

namespace App\Services\Workflow;

use App\Contracts\CompanyContext;
use App\Contracts\HasWorkflow;
use App\Contracts\PresetSource;
use App\Services\Workflow\Effects\ApprovalRequest;
use App\Services\Workflow\Effects\NotifyOwnerWa;
use App\Services\Workflow\Effects\WorkflowEffect;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use LogicException;
use Throwable;

class WorkflowEngine
{
    /** @var array<string, WorkflowEffect> */
    private array $effects;

    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly PresetSource $presetSource,
        private readonly JsonWorkflowLog $log,
        ApprovalRequest $approvalRequest,
        NotifyOwnerWa $notifyOwnerWa,
    ) {
        $this->effects = [
            $approvalRequest->key() => $approvalRequest,
            $notifyOwnerWa->key() => $notifyOwnerWa,
        ];
    }

    /** @return list<array{code: string, label: string}> */
    public function stages(string $entity): array
    {
        return $this->workflowDefinition($entity)['stages'];
    }

    /** @return list<array<string, mixed>> */
    public function availableTransitions(HasWorkflow $model, string $actorRole): array
    {
        $snapshot = $this->snapshot($model);
        $workflow = $this->workflowForSnapshot($snapshot);
        $this->assertActorRole($actorRole);

        return array_values(array_filter(
            $workflow['transitions'],
            fn (array $transition): bool => $this->matchesSource($transition, $snapshot['stage'], $workflow)
                && in_array($actorRole, $transition['roles'], true),
        ));
    }

    /**
     * @return array{status: string, from: string, to: string, effects: list<array<string, mixed>>}
     */
    public function transition(
        HasWorkflow $model,
        string $to,
        string $actorRole,
        ?string $note = null,
    ): array {
        $snapshot = $this->snapshot($model);
        $workflow = $this->workflowForSnapshot($snapshot);
        $this->assertActorRole($actorRole);
        $transition = $this->findTransition($workflow, $snapshot['stage'], $to);

        if (! in_array($actorRole, $transition['roles'], true)) {
            throw new AuthorizationException('Role tidak diizinkan untuk transisi workflow.');
        }

        $note = $note === null ? null : trim($note);
        if (($transition['requires_note'] ?? false) === true && ($note === null || $note === '')) {
            throw new InvalidArgumentException('Transisi ini membutuhkan catatan alasan.');
        }

        $context = [
            'company' => $snapshot['company'],
            'preset' => $snapshot['preset'],
            'entity' => $snapshot['entity'],
            'record_id' => $snapshot['id'],
            'from' => $snapshot['stage'],
            'to' => $to,
            'actor_role' => $actorRole,
            'note' => $note,
        ];

        if (($transition['requires_approval'] ?? false) === true) {
            $effectResults = [$this->executeEffect('approval.request', $context)];
            $this->log->append($snapshot['company'], $this->logEntry('approval_requested', $context, $effectResults));

            return $this->result('pending_approval', $snapshot['stage'], $to, $effectResults);
        }

        $effectResults = [];
        foreach ($transition['effects'] ?? [] as $effect) {
            $effectResults[] = $this->executeEffect($effect, $context);
        }

        $model->setWorkflowStage($to);
        try {
            $this->log->append($snapshot['company'], $this->logEntry('transitioned', $context, $effectResults));
        } catch (Throwable $exception) {
            $model->setWorkflowStage($snapshot['stage']);
            throw $exception;
        }

        return $this->result('transitioned', $snapshot['stage'], $to, $effectResults);
    }

    /** @return array{company: string, preset: string, entity: string, id: string|int, stage: string} */
    private function snapshot(HasWorkflow $model): array
    {
        $company = $model->workflowCompany();
        $entity = $model->workflowEntity();
        $id = $model->workflowIdentifier();
        $stage = $model->workflowStage();
        $activeCompany = $this->companyContext->current();

        if ($company !== $activeCompany) {
            throw new LogicException('Akses workflow lintas company ditolak.');
        }
        $this->assertEntity($entity);
        if ($stage === '') {
            throw new InvalidArgumentException('Stage workflow tidak valid.');
        }

        return [
            'company' => $company,
            'preset' => $this->companyContext->preset(),
            'entity' => $entity,
            'id' => $id,
            'stage' => $stage,
        ];
    }

    /** @param array{company: string, preset: string, entity: string, id: string|int, stage: string} $snapshot @return array<string, mixed> */
    private function workflowForSnapshot(array $snapshot): array
    {
        $workflow = $this->workflowDefinition($snapshot['entity'], $snapshot['preset']);
        $stageCodes = array_column($workflow['stages'], 'code');
        if (! in_array($snapshot['stage'], $stageCodes, true)) {
            throw new InvalidArgumentException("Stage workflow tidak terdaftar: {$snapshot['entity']}.{$snapshot['stage']}");
        }

        return $workflow;
    }

    /** @return array<string, mixed> */
    private function workflowDefinition(string $entity, ?string $presetKey = null): array
    {
        $this->assertEntity($entity);
        $presetKey ??= $this->companyContext->preset();
        $preset = $this->presetSource->find($presetKey);
        if ($preset === null) {
            throw new InvalidArgumentException("Preset company tidak tersedia: {$presetKey}");
        }

        $workflow = $preset['workflows'][$entity] ?? null;
        if (! is_array($workflow)) {
            throw new InvalidArgumentException("Workflow tidak tersedia untuk entity: {$entity}");
        }

        return $workflow;
    }

    private function assertEntity(string $entity): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $entity)) {
            throw new InvalidArgumentException('Entity workflow tidak valid.');
        }
    }

    /** @param array<string, mixed> $workflow @return array<string, mixed> */
    private function findTransition(array $workflow, string $from, string $to): array
    {
        $matches = array_values(array_filter(
            $workflow['transitions'],
            fn (array $transition): bool => $transition['to'] === $to
                && $this->matchesSource($transition, $from, $workflow),
        ));

        if ($matches === []) {
            throw new InvalidArgumentException("Transisi workflow tidak dideklarasikan: {$from} -> {$to}");
        }

        foreach ($matches as $match) {
            if ($match['from'] === $from) {
                return $match;
            }
        }

        return $matches[0];
    }

    /** @param array<string, mixed> $transition @param array<string, mixed> $workflow */
    private function matchesSource(array $transition, string $stage, array $workflow): bool
    {
        if ($transition['from'] === $stage) {
            return true;
        }

        return $transition['from'] === '*'
            && ! in_array($stage, $workflow['terminal'], true);
    }

    private function assertActorRole(string $actorRole): void
    {
        if (! in_array($actorRole, ['owner', 'staff', 'system'], true)) {
            throw new AuthorizationException('Role workflow tidak dikenal.');
        }
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function executeEffect(string $key, array $context): array
    {
        $effect = $this->effects[$key] ?? null;
        if ($effect === null) {
            throw new LogicException("Efek workflow belum diimplementasikan: {$key}");
        }

        return $effect->execute($context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<array<string, mixed>>  $effects
     * @return array<string, mixed>
     */
    private function logEntry(string $event, array $context, array $effects): array
    {
        return [
            'event' => $event,
            ...$context,
            'effects' => $effects,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $effects
     * @return array{status: string, from: string, to: string, effects: list<array<string, mixed>>}
     */
    private function result(string $status, string $from, string $to, array $effects): array
    {
        return compact('status', 'from', 'to', 'effects');
    }
}
