<?php

namespace App\Services\Workflow;

use App\Contracts\HasWorkflow;
use InvalidArgumentException;

class ArrayWorkflowRecord implements HasWorkflow
{
    /** @param array<string, mixed> $record */
    public function __construct(
        private readonly string $company,
        private readonly string $entity,
        private array $record,
    ) {
        if (! array_key_exists('id', $record) || (! is_string($record['id']) && ! is_int($record['id']))) {
            throw new InvalidArgumentException('Record workflow membutuhkan identifier.');
        }
        if (! isset($record['stage']) || ! is_string($record['stage']) || $record['stage'] === '') {
            throw new InvalidArgumentException('Record workflow membutuhkan stage.');
        }
    }

    public function workflowCompany(): string
    {
        return $this->company;
    }

    public function workflowEntity(): string
    {
        return $this->entity;
    }

    public function workflowIdentifier(): string|int
    {
        return $this->record['id'];
    }

    public function workflowStage(): string
    {
        return $this->record['stage'];
    }

    public function setWorkflowStage(string $stage): void
    {
        $this->record['stage'] = $stage;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->record;
    }
}
