<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIPASTI')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@auth
    <nav class="topbar">
        <a class="brand" href="{{ route('home') }}">SIPASTI</a>
        @can('case.view')
            <a href="{{ route('board') }}" @class(['active' => request()->routeIs('board', 'cases.*')])>Papan perkara</a>
        @endcan
        @can('case.register')
            <a href="{{ route('cases.create') }}">Registrasi perkara</a>
        @endcan
        @can('report.view')
            <a href="{{ route('reports.stage-durations') }}" @class(['active' => request()->routeIs('reports.*')])>Laporan durasi tahap</a>
        @endcan
        @can('indicators.manage')
            <a href="{{ route('indicators.index') }}" @class(['active' => request()->routeIs('indicators.*')])>Indikator</a>
        @endcan
        @can('audit.view')
            <a href="{{ route('audit.index') }}" @class(['active' => request()->routeIs('audit.*')])>Log audit</a>
        @endcan
        <span class="spacer"></span>
        <span class="small">{{ auth()->user()->name }} · {{ auth()->user()->roleLabel() }}</span>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </nav>
@endauth

<main>
    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="flash err">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash err">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
