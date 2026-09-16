<?php

namespace App\Contracts;

interface HasWorkflow
{
    public function workflowCompany(): string;

    public function workflowEntity(): string;

    public function workflowIdentifier(): string|int;

    public function workflowStage(): string;

    public function setWorkflowStage(string $stage): void;
}
