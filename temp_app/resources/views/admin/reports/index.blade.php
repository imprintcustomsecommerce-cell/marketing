@extends('layouts.admin')
@section('title', 'Reports')
@section('content')
<style>
    .report-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
    .report-card{display:flex;align-items:center;gap:14px;margin:0}
    .report-card .icon{width:42px;height:42px;border-radius:12px;background:var(--accent-bg);color:var(--accent-ink);display:grid;place-items:center;font-weight:800}
    .report-card .details{flex:1}.report-card h2{font-size:1rem}.report-card p{margin:3px 0 0}
</style>
<div class="topline"><div><span class="page-kicker">Downloads</span><h1>Reports</h1><div class="muted small">Export current workspace records as Excel-compatible CSV files.</div></div></div>
<div class="report-grid" style="margin-bottom:18px"><section class="card"><strong style="font-size:1.5rem">{{ $analytics['delivery'] }}</strong><div class="muted small">Average delivery time</div></section><section class="card"><strong style="font-size:1.5rem">{{ $analytics['overdue'] }}</strong><div class="muted small">Overdue production</div></section><section class="card"><strong style="font-size:1.5rem">{{ $analytics['revisions'] }}</strong><div class="muted small">Revisions per event</div></section><section class="card"><strong style="font-size:1.5rem">{{ $analytics['notifications'] }}</strong><div class="muted small">Client emails sent</div></section></div>
<div class="report-grid">
    @foreach($reports as $key => $label)
        <section class="card report-card">
            <span class="icon">CSV</span>
            <span class="details"><h2>{{ $label }}</h2><p class="muted small">Complete {{ str($label)->lower() }} report</p></span>
            <a class="button ghost" href="{{ route('admin.reports.download', $key) }}">Download</a>
        </section>
    @endforeach
</div>
@endsection
