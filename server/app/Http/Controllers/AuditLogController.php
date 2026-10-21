<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->input('action'), fn ($q, $v) => $q->where('action', 'like', strtoupper($v).'%'))
            ->when($request->input('user_id'), fn ($q, $v) => $q->where('user_id', (int) $v))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')
            ->limit(300)
            ->get();

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('audit.index', ['logs' => $logs, 'actions' => $actions, 'filters' => $request->only(['action', 'user_id', 'from', 'to'])]);
    }
}
