<?php

namespace App\Livewire;

use App\Admin\SiteGridReader;
use App\Models\Site;
use Livewire\Component;

class IncidentsPage extends Component
{
    public string $activeTab = 'reserves'; // 'reserves' or 'primary'

    public function selectTab(string $tab): void
    {
        if (in_array($tab, ['reserves', 'primary'])) {
            $this->activeTab = $tab;
        }
    }

    public function render()
    {
        $reader = app(SiteGridReader::class);
        $sites = Site::query()
            ->with(['tokens', 'group'])
            ->orderBy('domain')
            ->get();

        $onReserveSites = [];
        $onPrimarySites = [];

        foreach ($sites as $site) {
            $hasReserve = false;
            $slotsInfo = [];

            try {
                $rows = $reader->forSite($site);

                foreach ($rows['phone'] ?? [] as $row) {
                    $state = $row['state'] ?? 'ok';
                    if ($state === 'on_reserve') {
                        $hasReserve = true;
                    }
                    $slotsInfo[] = [
                        'type' => 'phone',
                        'key' => $row['key'],
                        'state' => $state,
                    ];
                }

                foreach ($rows['messenger'] ?? [] as $row) {
                    $state = $row['state'] ?? 'ok';
                    if ($state === 'on_reserve') {
                        $hasReserve = true;
                    }
                    $slotsInfo[] = [
                        'type' => 'messenger',
                        'key' => $row['key'],
                        'state' => $state,
                    ];
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $siteData = [
                'model' => $site,
                'slots' => $slotsInfo,
                'hasReserve' => $hasReserve,
            ];

            if ($hasReserve) {
                $onReserveSites[] = $siteData;
            } else {
                $onPrimarySites[] = $siteData;
            }
        }

        return view('livewire.incidents-page', [
            'onReserveSites' => $onReserveSites,
            'onPrimarySites' => $onPrimarySites,
            'reservesCount' => count($onReserveSites),
            'primaryCount' => count($onPrimarySites),
        ])->layout('components.layouts.admin');
    }
}
