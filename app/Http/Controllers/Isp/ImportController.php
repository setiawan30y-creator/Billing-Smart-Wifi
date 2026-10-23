<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportBatch;
use App\Models\ImportBatch;
use App\Services\AuditLogger;
use App\Services\CustomerImportService;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ImportController extends Controller
{
    public function index()
    {
        return view('isp.imports.index', [
            'batches' => ImportBatch::with('creator')->latest()->paginate(15),
            'columns' => CustomerImportService::COLUMNS,
        ]);
    }

    public function template(Request $request, CustomerImportService $service)
    {
        $rows = $service->templateRows();
        if ($request->query('format') === 'csv') {
            $out = "\xEF\xBB\xBF";
            foreach ($rows as $r) {
                $out .= implode(';', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $r))."\r\n";
            }

            return response($out, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="template-import-pelanggan.csv"']);
        }

        return response()->download(Spreadsheet::writeXlsx($rows, 'Pelanggan'), 'template-import-pelanggan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function store(Request $request, CustomerImportService $service)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt']], [], ['file' => 'file']);
        try {
            $batch = $service->upload($this->tenant(), $request->file('file'));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        } catch (Throwable $e) {
            return back()->with('error', 'File tidak dapat dibaca: '.mb_substr($e->getMessage(), 0, 150));
        }

        return redirect()->route('app.imports.show', $batch)->with('success', 'File dibaca. Periksa hasil validasi sebelum mengimport.');
    }

    public function show(Request $request, ImportBatch $import)
    {
        $filter = $request->query('filter');

        return view('isp.imports.show', [
            'batch' => $import,
            'rows' => $import->rows()->when($filter, fn ($q) => $filter === 'warning' ? $q->whereNotNull('warnings') : $q->where('status', $filter))->paginate(50)->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function execute(ImportBatch $import)
    {
        if ($import->status !== 'validated') {
            return back()->with('error', 'Batch ini sudah diproses atau dibatalkan.');
        }
        if ($import->valid_rows === 0) {
            return back()->with('error', 'Tidak ada baris valid untuk diimport.');
        }
        $import->update(['executed_by' => \Illuminate\Support\Facades\Auth::guard('web')->id(), 'executed_at' => now()]);
        AuditLogger::log('import.executed', $import, null, ['valid_rows' => $import->valid_rows, 'error_rows' => $import->error_rows]);
        ProcessImportBatch::dispatch($this->tenant()->id, $import->id);

        return redirect()->route('app.imports.show', $import)->with('success', config('queue.default') === 'sync'
            ? 'Import selesai diproses.'
            : 'Import sedang diproses di antrean. Muat ulang halaman untuk melihat hasilnya.');
    }

    public function cancel(ImportBatch $import)
    {
        if ($import->status !== 'validated') {
            return back()->with('error', 'Batch ini tidak dapat dibatalkan.');
        }
        $import->update(['status' => 'cancelled']);
        AuditLogger::log('import.cancelled', $import);

        return redirect()->route('app.imports.index')->with('success', 'Import dibatalkan. Tidak ada data pelanggan yang dibuat.');
    }

    /** Laporan kesalahan per baris (CSV) untuk diperbaiki lalu diunggah ulang. */
    public function errors(ImportBatch $import)
    {
        $cols = array_keys(CustomerImportService::COLUMNS);
        $out = "\xEF\xBB\xBF".implode(';', array_merge(['baris'], $cols, ['kesalahan']))."\r\n";
        $import->rows()->whereIn('status', ['error', 'failed'])->each(function ($r) use (&$out, $cols) {
            $vals = array_merge([$r->row_number], array_map(fn ($c) => $r->data[$c] ?? '', $cols), [implode(' | ', $r->errors ?? [])]);
            $out .= implode(';', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $vals))."\r\n";
        });

        return response($out, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="kesalahan-import-'.$import->id.'.csv"']);
    }
}
