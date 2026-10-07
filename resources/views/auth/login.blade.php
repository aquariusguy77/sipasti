@extends('layouts.app')

@section('title', 'Masuk · SIPASTI')

@section('content')
<div class="login-box">
    <div class="card">
        <h1>SIPASTI</h1>
        <p class="muted small">Sistem Informasi Pemantauan Alur dan Status Detensi Imigrasi. Purwarupa dengan data bentukan.</p>

        <form method="post" action="{{ url('/login') }}">
            @csrf
            <label for="email">Surel</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            <label for="password">Kata sandi</label>
            <input id="password" name="password" type="password" required>
            <p><button type="submit">Masuk</button></p>
        </form>
    </div>

    @unless (app()->isProduction())
    <div class="card">
        <h3>Akun demonstrasi</h3>
        <p class="muted small">Kata sandi seluruh akun: <span class="mono">password</span></p>
        <table class="demo-accounts">
            @foreach (config('sipasti.roles') as $role => $label)
                <tr><td class="mono">{{ $role }}@sipasti.test</td><td>{{ $label }}</td></tr>
            @endforeach
        </table>
    </div>
    @endunless
</div>
@endsection
