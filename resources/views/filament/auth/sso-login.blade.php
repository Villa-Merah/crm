@php
    $ssoError = $errors->first('sso') ?: $errors->first('state') ?: $errors->first('code');
@endphp
@if ($ssoError)
    <p role="alert" style="margin-top: 1rem; padding: .75rem; border-radius: .5rem; background: #fff1f2; color: #be123c; font-size: .875rem;">{{ $ssoError }}</p>
@endif
@if (config('sso.enabled'))
    <div style="margin-top: 1rem;">
        <x-filament::button tag="a" :href="route('sso.login')" outlined class="w-full">Masuk dengan akun SIVMI</x-filament::button>
    </div>
@endif
