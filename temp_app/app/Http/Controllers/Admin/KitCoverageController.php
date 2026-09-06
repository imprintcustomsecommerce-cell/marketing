<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Endorser;
use App\Models\PrKit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The monthly picture of the house rule: every active endorser gets one kit a
 * month, and each kit owes two pieces of content. This answers "who is covered
 * this month, and who still owes us video" in one screen.
 */
class KitCoverageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $month = $this->month($request->query('month'));

        $kits = PrKit::with('obligations')
            ->where('purpose', PrKit::PURPOSE_ENDORSER)
            ->whereNotNull('endorser_id')
            ->whereBetween('delivery_date', [$month->startOfMonth(), $month->endOfMonth()])
            ->get()
            ->groupBy('endorser_id');

        $rows = Endorser::whereIn('status', ['active', 'pending'])->orderBy('name')->get()
            ->map(function (Endorser $endorser) use ($kits) {
                $endorserKits = $kits->get($endorser->id, collect());
                $obligations = $endorserKits->flatMap->obligations;

                return [
                    'endorser' => $endorser,
                    'kits' => $endorserKits,
                    'quota' => $endorserKits->sum('content_quota'),
                    'done' => $obligations->whereIn('status', ['completed', 'waived'])->count(),
                    'submitted' => $obligations->where('status', 'submitted')->count(),
                    'pending' => $obligations->where('status', 'pending')->count(),
                ];
            });

        return view('admin.kit-coverage', [
            'month' => $month,
            'rows' => $rows,
            'previous' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'withoutKit' => $rows->filter(fn (array $row): bool => $row['kits']->isEmpty())->count(),
        ]);
    }

    private function month(?string $value): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::createFromFormat('Y-m', $value)->startOfMonth() : CarbonImmutable::now()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }
}
