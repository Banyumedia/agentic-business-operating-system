<?php

namespace App\Services\Workflow\Effects;

use App\Contracts\EntityRepository;
use App\Models\ApprovalTicket;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class ApprovalRequest implements WorkflowEffect
{
    public function __construct(private readonly EntityRepository $repository) {}

    public function key(): string
    {
        return 'approval.request';
    }

    private function generateCode(string $companyId, ?EntityRepository $repository = null): string
    {
        do {
            // 4-6 digits for WA reply (D-27)
            $code = (string) random_int(1000, 999999);

            $exists = $repository === null
                ? ApprovalTicket::where('company_id', $companyId)
                    ->whereIn('status', ['prepared', 'pending'])
                    ->where('code', $code)
                    ->exists()
                : collect($repository->all())->contains(
                    static fn (array $row): bool => in_array($row['status'] ?? null, ['prepared', 'pending'], true)
                        && ($row['code'] ?? null) === $code,
                );
        } while ($exists);

        return $code;
    }

    public function execute(array $context): array
    {
        $operationBase = $this->operationBase($context);

        if (config('datasource.driver') === 'eloquent') {
            return $this->executeEloquent($context, $operationBase);
        }

        $repository = $this->repository->for((string) $context['company'], 'approval_tickets');
        $rows = $repository->all();
        $related = collect($rows)->filter(
            static fn (array $row): bool => ($row['payload']['operation_base'] ?? null) === $operationBase,
        );
        $related = $related->map(function (array $row) use ($repository): array {
            if (! in_array($row['status'] ?? null, ['prepared', 'pending'], true)
                || ! $this->isExpired($row)) {
                return $row;
            }

            return $repository->save([
                ...$row,
                'status' => 'expired',
                'responded_at' => now()->toIso8601String(),
            ]);
        });
        $existing = $related->first(
            static fn (array $row): bool => in_array($row['status'] ?? null, ['prepared', 'pending'], true),
        );

        if (is_array($existing)) {
            return $this->result($existing, (string) $existing['operation_id']);
        }

        $operationId = hash('sha256', $operationBase.':attempt:'.($related->count() + 1));
        $subjectId = filter_var($context['record_id'] ?? null, FILTER_VALIDATE_INT);
        try {
            $ticket = $repository->save([
                'operation_id' => $operationId,
                'code' => $this->generateCode((string) $context['company'], $repository),
                'action_type' => 'workflow.transition',
                'subject_type' => null,
                'subject_id' => $subjectId === false ? null : $subjectId,
                'payload' => [...$context, 'operation_base' => $operationBase],
                'amount' => null,
                'requested_by_user_id' => auth()->id(),
                // Prepared is intentionally invisible to the dashboard until the
                // audit log is durable. Retry reuses the same operation/ticket.
                'status' => 'prepared',
                'channel' => 'whatsapp',
                'expires_at' => now()->addHours(24)->toIso8601String(),
            ]);
        } catch (InvalidArgumentException $exception) {
            // A concurrent request may win the unique operation_id race after
            // our initial read. Reuse that ticket instead of duplicating it.
            $ticket = collect($repository->all())->first(
                static fn (array $row): bool => ($row['operation_id'] ?? null) === $operationId,
            );
            if (! is_array($ticket)) {
                throw $exception;
            }
        }

        return $this->result($ticket, $operationId);
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $result */
    public function commit(array $context, array $result): void
    {
        if (config('datasource.driver') === 'eloquent') {
            $ticket = ApprovalTicket::query()
                ->where('company_id', $context['company'])
                ->find($result['ticket_id'] ?? null);
            if ($ticket === null
                || ($ticket->payload['operation_id'] ?? null) !== ($result['operation_id'] ?? null)) {
                throw new LogicException('Approval ticket Eloquent tidak cocok dengan operasi workflow.');
            }
            if ($ticket->status === 'pending') {
                return;
            }
            if ($ticket->status !== 'prepared') {
                throw new LogicException('Approval ticket Eloquent tidak berada pada state prepared.');
            }

            $ticket->update(['status' => 'pending']);

            return;
        }

        [$repository, $ticket] = $this->ticketFor($context, $result);
        if (($ticket['status'] ?? null) === 'pending') {
            return;
        }
        if (($ticket['status'] ?? null) !== 'prepared') {
            throw new LogicException('Approval ticket tidak berada pada state prepared.');
        }

        $repository->save([...$ticket, 'status' => 'pending']);
    }

    /** @param array<string, mixed> $context */
    private function operationBase(array $context): string
    {
        $identity = array_intersect_key($context, array_flip([
            'company',
            'preset',
            'entity',
            'record_id',
            'from',
            'to',
            'actor_role',
            'note',
        ]));
        $identity['requested_by_user_id'] = auth()->id();

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function executeEloquent(array $context, string $operationBase): array
    {
        return DB::transaction(function () use ($context, $operationBase): array {
            Company::query()->whereKey($context['company'])->lockForUpdate()->firstOrFail();

            $related = ApprovalTicket::query()
                ->where('company_id', $context['company'])
                ->where('action_type', 'workflow.transition')
                ->where('subject_id', $context['record_id'])
                ->get()
                ->filter(static fn (ApprovalTicket $ticket): bool => ($ticket->payload['operation_base'] ?? null) === $operationBase);

            foreach ($related as $ticket) {
                if (in_array($ticket->status, ['prepared', 'pending'], true) && $ticket->isExpired()) {
                    $ticket->update(['status' => 'expired', 'responded_at' => now()]);
                }
            }

            $existing = $related->first(
                static fn (ApprovalTicket $ticket): bool => in_array($ticket->status, ['prepared', 'pending'], true),
            );
            if ($existing instanceof ApprovalTicket) {
                return $this->eloquentResult($existing);
            }

            $operationId = hash('sha256', $operationBase.':attempt:'.($related->count() + 1));
            $ticket = ApprovalTicket::create([
                'company_id' => $context['company'],
                'code' => $this->generateCode((string) $context['company']),
                'action_type' => 'workflow.transition',
                'subject_type' => null,
                'subject_id' => $context['record_id'],
                'payload' => [...$context, 'operation_base' => $operationBase, 'operation_id' => $operationId],
                'amount' => null,
                'requested_by_user_id' => auth()->id(),
                'status' => 'prepared',
                'channel' => 'whatsapp',
                'expires_at' => now()->addHours(24),
            ]);

            return $this->eloquentResult($ticket);
        });
    }

    /** @return array<string, mixed> */
    private function eloquentResult(ApprovalTicket $ticket): array
    {
        return [
            'effect' => $this->key(),
            'status' => 'pending',
            'ticket_id' => (string) $ticket->id,
            'operation_id' => (string) $ticket->payload['operation_id'],
        ];
    }

    /** @param array<string, mixed> $ticket */
    private function isExpired(array $ticket): bool
    {
        $expiresAt = $ticket['expires_at'] ?? null;

        return ! is_string($expiresAt)
            || CarbonImmutable::parse($expiresAt)->lessThanOrEqualTo(now());
    }

    /** @param array<string, mixed> $ticket @return array<string, mixed> */
    private function result(array $ticket, string $operationId): array
    {
        return [
            'effect' => $this->key(),
            'status' => 'pending',
            'ticket_id' => (string) $ticket['id'],
            'operation_id' => $operationId,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $result
     * @return array{0: EntityRepository, 1: ?array<string, mixed>, 2: int}
     */
    private function ticketFor(array $context, array $result): array
    {
        $company = (string) ($context['company'] ?? '');
        $ticketId = filter_var($result['ticket_id'] ?? null, FILTER_VALIDATE_INT);
        if ($company === '' || $ticketId === false) {
            throw new LogicException('Approval ticket tidak memiliki scope atau ID yang valid.');
        }

        $repository = $this->repository->for($company, 'approval_tickets');
        $ticket = $repository->find($ticketId);
        if ($ticket === null) {
            throw new LogicException('Approval ticket yang akan dikomit tidak ditemukan.');
        }
        if ($ticket !== null && (($ticket['action_type'] ?? null) !== 'workflow.transition'
            || (string) ($ticket['subject_id'] ?? '') !== (string) ($context['record_id'] ?? '')
            || ($ticket['operation_id'] ?? null) !== ($result['operation_id'] ?? null))) {
            throw new LogicException('Approval ticket tidak cocok dengan operasi workflow.');
        }

        return [$repository, $ticket, $ticketId];
    }
}
