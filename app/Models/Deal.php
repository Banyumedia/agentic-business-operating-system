<?php

namespace App\Models;

use App\Contracts\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model implements HasWorkflow
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'contact_id',
        'title',
        'stage',
        'value',
        'expected_close_date',
        'owner_user_id',
        'attributes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_date' => 'date',
        'attributes' => 'array',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function workflowCompany(): string
    {
        return (string) $this->company_id;
    }

    public function workflowEntity(): string
    {
        return 'deals';
    }

    public function workflowIdentifier(): string|int
    {
        return $this->id ?? 0;
    }

    public function workflowStage(): string
    {
        return $this->stage;
    }

    public function setWorkflowStage(string $stage): void
    {
        $this->stage = $stage;
        $this->save();
    }
}
