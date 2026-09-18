<?php

namespace App\Services\Addons\Storage;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Services\FeatureResolver;
use RuntimeException;

class CompanyDiskResolver
{
    // Default BYOS quota: 100MB
    public const DEFAULT_QUOTA_BYTES = 100 * 1024 * 1024;

    // Managed storage addon quota: 5GB
    public const MANAGED_QUOTA_BYTES = 5 * 1024 * 1024 * 1024;

    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly FeatureResolver $featureResolver,
    ) {}

    /**
     * Get the configured filesystem disk name for the current company.
     */
    public function getDiskName(): string
    {
        if ($this->featureResolver->enabled('addon.managed_storage')) {
            return 's3-managed';
        }

        return 'google-drive'; // Default BYOS D-22
    }

    /**
     * Get the maximum allowed quota in bytes.
     */
    public function getQuotaBytes(): int
    {
        if ($this->featureResolver->enabled('addon.managed_storage')) {
            return self::MANAGED_QUOTA_BYTES;
        }

        return self::DEFAULT_QUOTA_BYTES;
    }

    /**
     * Check if a new file of size $fileSizeBytes can be uploaded.
     * Throws an exception if quota is exceeded.
     */
    public function authorizeUpload(int $fileSizeBytes, int $currentUsageBytes): void
    {
        if ($currentUsageBytes + $fileSizeBytes > $this->getQuotaBytes()) {
            throw new RuntimeException('Storage quota exceeded.');
        }
    }
}
