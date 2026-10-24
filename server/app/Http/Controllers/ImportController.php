<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\ImportService;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $imports) {}

    public function index()
    {
        return view('imports.index', [
            'batches' => ImportBatch::query()->with('creator:id,name')->latest('created_at')->limit(30)->get(),
            // Waiting-room staging lives in the session (DB-backed in
            // production), so a refresh - or even a mid-session deploy -
            // brings the reviewed rows straight back.
            'staged' => session('import_staging'),
        ]);
    }

    public function products(\Illuminate\Http\Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        $rows = $this->imports->readSpreadsheet($request->file('file'));
        $batch = $this->imports->importProducts($rows, $request->file('file')->getClientOriginalName(), includeStock: true);

        return redirect()->route('imports.index')->with('success',
            sprintf('%s: %d products imported/updated, %d rows failed.',
            $batch->batch_number, $batch->successful_rows, $batch->failed_rows));
    }

    public function stock(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'mode' => ['required', 'in:add,set'],
        ]);

        $rows = $this->imports->readSpreadsheet($request->file('file'));
        $batch = $this->imports->importStock($rows, $request->file('file')->getClientOriginalName(), $request->input('mode'));
        $label = $request->input('mode') === 'set' ? 'stock set to physical count' : 'stock quantities added';

        return redirect()->route('imports.index')->with('success',
            sprintf('%s: %d rows applied (%s).', $batch->batch_number, $batch->successful_rows, $label));
    }

    /** Flutter parity: dry-run analysis for the preview dialog. */
    public function preview(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'kind' => ['required', 'in:products,stock'],
            'mode' => ['nullable', 'in:add,set'],
        ]);

        $rows = $this->imports->readSpreadsheet($request->file('file'));
        $stats = $this->imports->analyze($rows, $request->input('kind'));
        $stats['file'] = $request->file('file')->getClientOriginalName();
        $stats['mode'] = $request->input('mode') ?? 'add';
        $stats['rows'] = $this->imports->draftRows($rows, $request->input('kind'), $stats['mode']);

        // Persist so the waiting room survives refreshes/deploys (see index()).
        $request->session()->put('import_staging', $stats);

        return response()->json($stats);
    }

    /**
     * Waiting-room commit: applies exactly the rows the user reviewed and
     * edited on the imports page, with per-row include flags.
     */
    public function commit(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:products,stock'],
            'mode' => ['required', 'in:add,set'],
            'file_name' => ['required', 'string', 'max:255'],
            'rows' => ['required', 'array', 'max:5000'],
            'rows.*.include' => ['nullable'],
            'rows.*.sku' => ['nullable', 'string', 'max:255'],
            'rows.*.line' => ['nullable', 'string', 'max:10'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.barcode' => ['nullable', 'string', 'max:64'],
            'rows.*.category' => ['nullable', 'string', 'max:255'],
            'rows.*.brand' => ['nullable', 'string', 'max:255'],
            'rows.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.reorder_level' => ['nullable', 'numeric', 'min:0'],
            'rows.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $kind = $data['kind'];
        $kept = [];

        foreach ($data['rows'] as $r) {
            if (empty($r['include'])) { continue; }
            if (trim((string) ($r['sku'] ?? '')) === '') { continue; }
            $kept[] = $r;
        }

        if ($kept === []) {
            return back()->withErrors(['rows' => 'Nothing to import — no rows are marked for inclusion.']);
        }

        if ($kind === 'products') {
            $normalized = collect($kept)->map(fn ($r) => collect([
                $r['sku'],
                $r['barcode'] ?? '',
                $r['name'] ?? '',
                $r['category'] ?? '',
                $r['brand'] ?? '',
                $r['cost_price'] ?? 0,
                $r['selling_price'] ?? 0,
                $r['tax_rate'] ?? 0,
                $r['reorder_level'] ?? 0,
                $r['quantity'] ?? 0,
            ]));
            $batch = $this->imports->importProducts($normalized, $data['file_name'], includeStock: true);
            $request->session()->forget('import_staging');

            return redirect()->route('imports.index')->with('success',
                sprintf('%s: %d products imported/updated, %d rows failed.',
                $batch->batch_number, $batch->successful_rows, $batch->failed_rows));
        }

        $normalized = collect($kept)->map(fn ($r) => collect([
            $r['sku'],
            $r['quantity'] ?? 0,
        ]));
        $batch = $this->imports->importStock($normalized, $data['file_name'], $data['mode']);
        $label = $data['mode'] === 'set' ? 'stock set to physical count' : 'stock quantities added';
        $request->session()->forget('import_staging');
        $label = $data['mode'] === 'set' ? 'stock set to physical count' : 'stock quantities added';

        return redirect()->route('imports.index')->with('success',
            sprintf('%s: %d rows applied (%s).', $batch->batch_number, $batch->successful_rows, $label));
    }

    /** Throw away staged waiting-room rows without importing anything. */
    public function discardStaging(\Illuminate\Http\Request $request)
    {
        $request->session()->forget('import_staging');

        return redirect()->route('imports.index')->with('warning', 'Staged waiting-room rows discarded — nothing was imported.');
    }

    /** Downloadable CSV template (Flutter template downloads parity). */
    public function template(\Illuminate\Http\Request $request)
    {
        $kind = $request->input('kind', 'products');

        if ($kind === 'stock') {
            $headers = ['sku', 'quantity'];
            $sample = [['GT-112V', '10'], ['SAMPLE-SKU', '0']];
            $name = 'stock-import-template.csv';
        } else {
            $headers = ['sku', 'barcode', 'name', 'category', 'brand', 'cost_price', 'selling_price', 'tax_rate', 'reorder_level', 'quantity'];
            $sample = [['GT-112V', '2001234567895', 'Yamaha Pacifica 112V', 'Guitars', 'Yamaha', '1450', '2450', '15', '3', '10']];
            $name = 'product-import-template.csv';
        }

        return response()->streamDownload(function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($sample as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
