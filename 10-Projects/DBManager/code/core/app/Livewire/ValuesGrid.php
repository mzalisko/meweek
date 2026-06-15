<?php

namespace App\Livewire;

use App\Admin\SiteGridReader;
use App\Models\Site;
use Livewire\Component;

class ValuesGrid extends Component
{
    public ?int $site = null;

    public ?string $search = null;
    public ?string $type   = null;
    public ?string $geo    = null;
    public ?string $status = null;

    public function mount(?int $site = null): void
    {
        $this->site = $site ?? Site::query()->orderBy('id')->value('id');
    }

    public function render()
    {
        $siteModel = $this->site ? Site::with('group')->find($this->site) : null;
        $rows = $siteModel ? app(SiteGridReader::class)->forSite($siteModel) : [];
        $rows = $this->applyFilters($rows);

        return view('livewire.values-grid', [
            'siteModel' => $siteModel,
            'rows'      => $rows,
        ])->layout('components.layouts.admin');
    }

    private function applyFilters(array $rows): array
    {
        // Filter by type: keep only that type group
        if ($this->type !== null && $this->type !== '') {
            $rows = isset($rows[$this->type]) ? [$this->type => $rows[$this->type]] : [];
        }

        $search = $this->search !== null ? mb_strtolower($this->search) : null;
        $geo    = $this->geo    !== null && $this->geo    !== '' ? $this->geo    : null;
        $status = $this->status !== null && $this->status !== '' ? $this->status : null;

        if ($search === null && $geo === null && $status === null) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $type => $items) {
            $kept = array_filter($items, function (array $row) use ($search, $geo, $status): bool {
                if ($search !== null && !str_contains(mb_strtolower($row['key']), $search)) {
                    return false;
                }
                if ($geo !== null && !in_array($geo, $row['geo'], true)) {
                    return false;
                }
                if ($status !== null && $row['state'] !== $status) {
                    return false;
                }
                return true;
            });

            if (!empty($kept)) {
                $filtered[$type] = array_values($kept);
            }
        }

        return $filtered;
    }
}
