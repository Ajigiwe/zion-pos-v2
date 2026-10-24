@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Deploy</h5><small class="text-muted ms-1">Ship the repository into production, Superadmin only.</small>
@endsection

@section('content')
<div style="max-width:860px;">
    @if(session('deploy_result'))
        <div class="feature-card p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-2 text-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
                <strong>Deployed {{ session('deploy_result.sha') }}</strong>
            </div>
            <div class="small text-muted mb-1">deployed at {{ session('deploy_result.deployed_at') }} by {{ session('deploy_result.by') }}</div>
            @if(session('deploy_steps'))
                <pre class="small mb-0" style="white-space:pre-wrap;color:var(--muted);">@foreach(session('deploy_steps') as $s){{ $s }}
@endforeach</pre>
            @endif
        </div>
    @elseif(session('deploy_error'))
        <div class="feature-card p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-2" style="color:#dc2626;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <strong>Deploy failed</strong>
            </div>
            <div class="small mb-1">{{ session('deploy_error') }}</div>
            @if(session('deploy_steps'))
                <pre class="small mb-0" style="white-space:pre-wrap;color:var(--muted);">@foreach(session('deploy_steps') as $s){{ $s }}
@endforeach</pre>
            @endif
        </div>
    @endif

    <div class="feature-card p-3 mb-3">
        <div class="fw-bold small mb-2" style="letter-spacing:.5px;color:var(--muted);">WHAT WILL BE SERVED</div>
        <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-start align-items-md-center">
            <div class="min-w-0">
                @if($state)
                    <div class="fw-bold fs-6">Live Commit: <code>{{ substr($state['sha'],0,8) }}</code></div>
                    <div class="text-muted small">Deployed {{ $state['deployed_at'] }} by {{ $state['by'] }}</div>
                @else
                    <div class="fw-bold text-muted">manual zip deploy (no pull state yet)</div>
                @endif
            </div>
            <div class="text-start text-md-end min-w-0 w-100 w-md-auto pt-2 pt-md-0 border-top border-md-0">
                @if(is_array($remote) && ($remote['sha'] ?? false))
                    <div class="small">
                        <div class="d-flex align-items-center gap-1 justify-content-start justify-content-md-end flex-wrap">
                            <span class="text-muted">GitHub {{ \App\Http\Controllers\DeployController::BRANCH }}:</span>
                            <code class="fw-bold">{{ substr($remote['sha'],0,8) }}</code>
                            @if($state && ($remote['sha'] == ($state['sha'] ?? '')))
                                <span class="badge rounded-pill bg-success text-white">Up to date</span>
                            @else
                                <span class="badge rounded-pill bg-warning text-dark">Update available</span>
                            @endif
                        </div>
                        @if($remote['message'])
                            <div class="text-muted mt-1 small" style="max-width:440px;word-break:break-word;line-height:1.3;" title="{{ $remote['message'] }}">{{ $remote['message'] }}</div>
                        @endif
                    </div>
                @else
                    <span class="text-muted small">GitHub unreachable from this server right now</span>
                @endif
            </div>
        </div>
    </div>

    <div class="feature-card p-3 mb-3">
        <div class="fw-bold small mb-2" style="letter-spacing:.5px;color:var(--muted);">SHIP</div>
        <form method="POST" action="{{ route('deploy.run') }}" id="deploy-form">
            @csrf
            <div class="row g-2 align-items-center">
                <div class="col-12 col-sm-auto">
                    <label class="text-muted small fw-semibold mb-0">Pin a commit (optional):</label>
                </div>
                <div class="col-12 col-sm">
                    <input name="pin" placeholder="{{ $remote['sha'] ?? 'latest ' . \App\Http\Controllers\DeployController::BRANCH }}" class="form-control form-control-sm mono w-100" style="max-width:340px;">
                </div>
                <div class="col-12 col-sm-auto">
                    <button type="submit" class="btn btn-sm btn-accent w-100 w-sm-auto px-3" id="deploy-btn">Deploy now</button>
                </div>
            </div>
        </form>

        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const f = document.getElementById('deploy-form');
                f.addEventListener('submit', (e) => {
                    document.getElementById('deploy-btn').textContent = 'Working - do not close this page...';
                    document.getElementById('deploy-btn').disabled = true;
                });
            });
        </script>
        @endpush
    </div>

    <div class="feature-card p-3">
        <div class="fw-bold small mb-2" style="letter-spacing:.5px;color:var(--muted);">HOW A DEPLOY WORKS</div>
        <ol class="small text-muted mb-0">
            <li>Reads the latest commit of the repo.</li>
            <li>Downloads the GitHub source snapshot (no vendor inside).</li>
            <li>Stages it next to the live folder; copies your production <code>.env</code>, <code>vendor/</code>, <code>storage/</code> and <code>bootstrap/cache</code> into the stage.</li>
            <li>Swaps the folder atomically (rolling back automatically if the swap fails).</li>
            <li>Removes the old tree, keeps the snapshot zip.</li>
        </ol>
        <div class="text-muted mt-2" style="font-size:0.78rem;">Code changes deploy in about a minute, hands-free. When <code>composer.lock</code> itself changes (a dependency was added), the fast path here cannot rebuild vendor on shared hosting - ship that one via the FTP lane (<code>deploy\build.ps1</code> + <code>push.ps1</code>), which packs a fresh vendor tree.</div>
    </div>
</div>
@endsection
