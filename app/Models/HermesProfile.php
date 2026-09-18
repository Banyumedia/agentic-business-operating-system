<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class HermesProfile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_ping_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(HermesNode::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'hermes_profile_companies')
            ->withPivot(['role', 'is_default', 'created_at']);
    }

    public function conversationContexts(): HasMany
    {
        return $this->hasMany(HermesConversationContext::class);
    }

    protected static function booted(): void
    {
        static::saving(function (HermesProfile $profile) {
            if ($profile->type === 'primary') {
                $existingPrimary = static::where('owner_user_id', $profile->owner_user_id)
                    ->where('type', 'primary')
                    ->where('id', '!=', $profile->id)
                    ->exists();

                if ($existingPrimary) {
                    throw new LogicException('An owner can only have one primary profile.');
                }
            }

            if ($profile->type === 'addon' && empty($profile->billing_addon_id)) {
                throw new LogicException('An addon profile must have a billing_addon_id.');
            }
        });
    }
}
