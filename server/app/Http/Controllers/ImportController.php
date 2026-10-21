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
        ]);

        $rows = $this->imports->readSpreadsheet($request->file('file'));
        $stats = $this->imports->analyze($rows, $request->input('kind'));
        $stats['file'] = $request->file('file')->getClientOriginalName();

        return response()->json($stats);
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
