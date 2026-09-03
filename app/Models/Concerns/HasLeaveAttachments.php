<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\LeaveAttachmentService;

trait HasLeaveAttachments
{
    /**
     * @return list<string>
     */
    public function attachmentPaths(): array
    {
        return app(LeaveAttachmentService::class)->normalizeStoredPaths(
            is_string($this->attachment_path) ? $this->attachment_path : null,
            $this->attachment_paths ?? null,
        );
    }

    /**
     * @return list<string>
     */
    public function attachmentUrls(): array
    {
        return app(LeaveAttachmentService::class)->publicUrls($this->attachmentPaths());
    }
}
