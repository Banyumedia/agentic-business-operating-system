<?php

namespace App\Services\Workflow\Effects;

interface WorkflowEffect
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(array $context): array;
}
