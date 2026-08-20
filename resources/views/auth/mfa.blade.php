@extends('layouts.app')

@section('title', 'Vérification MFA')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card p-4">
            <h4 class="mb-3 text-center">Authentification à deux facteurs</h4>
            <p class="text-muted text-center">Saisissez le code à 6 chiffres de votre application d'authentification.</p>
            <form method="POST" action="{{ route('mfa.verify.post') }}">
                @csrf
                <div class="mb-3">
                    <input type="text" name="code" class="form-control form-control-lg text-center" placeholder="000000" maxlength="6" inputmode="numeric" required autofocus>
                </div>
                <button class="btn btn-primary w-100">Vérifier</button>
            </form>
        </div>
    </div>
</div>
@endsection
