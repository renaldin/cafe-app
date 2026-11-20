<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function types(Request $request)
    {
        $user = $request->user();
        $list = collect(ReportService::TYPES)->filter(fn ($v) => $user->can($v[1]))->map(fn ($v, $k) => ['key' => $k, 'title' => $v[0]])->values();

        return $this->ok($list);
    }

    public function show(Request $request, string $type, ReportService $reports)
    {
        abort_unless(isset(ReportService::TYPES[$type]), 404);
        abort_unless($request->user()->can(ReportService::TYPES[$type][1]), 403);
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group' => ['nullable', 'in:day,week,month'],
            'format' => ['nullable', 'in:json,xlsx,pdf'],
        ]);
        $report = $reports->run($type, $data['from'], $data['to'], ['group' => $data['group'] ?? 'day']);
        $format = $data['format'] ?? 'json';
        $filename = 'laporan-'.$type.'-'.$data['from'].'_'.$data['to'];

        if ($format === 'xlsx') {
            return Excel::download(new ArrayExport($report), $filename.'.xlsx');
        }
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf', ['report' => $report, 'cafe' => Settings::get('cafe_name')])
                ->setPaper('a4', count($report['columns']) > 6 ? 'landscape' : 'portrait');

            return $pdf->download($filename.'.pdf');
        }

        return $this->ok($report);
    }
}
