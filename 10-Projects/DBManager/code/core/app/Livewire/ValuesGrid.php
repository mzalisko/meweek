<?php

namespace App\Livewire;

use App\Admin\SiteGridReader;
use App\Models\Site;
use Livewire\Component;

class ValuesGrid extends Component
{
    public ?int $site = null;

    public function mount(?int $site = null): void
    {
        $this->site = $site ?? Site::query()->orderBy('id')->value('id');
    }

    public function render()
    {
        $siteModel = $this->site ? Site::with('group')->find($this->site) : null;
        $rows = $siteModel ? app(SiteGridReader::class)->forSite($siteModel) : [];

        return view('livewire.values-grid', [
            'siteModel' => $siteModel,
            'rows'      => $rows,
        ])->layout('components.layouts.admin');
    }
}
