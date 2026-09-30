<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\User;
use App\Services\Dashboard\Widget;

/**
 * Placeholder for a widget whose source module isn't built yet. Touches no
 * data. When the module lands, replace it in DashboardService with a real
 * widget (keeping the same key so the frontend slot lights up).
 */
final class PendingWidget extends Widget
{
    /**
     * @param  list<DashboardAudience>  $audiences
     */
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $module,
        private readonly array $audiences,
        private readonly ?string $permission = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function audiences(): array
    {
        return $this->audiences;
    }

    public function permission(): ?string
    {
        return $this->permission;
    }

    protected function data(User $user): array
    {
        return [];
    }

    public function toPayload(User $user): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'status' => 'pending',
            'module' => $this->module,
            'data' => null,
        ];
    }
}
