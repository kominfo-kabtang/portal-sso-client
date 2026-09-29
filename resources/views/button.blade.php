{{--
    Tombol login Portal ASN. Pemakaian:
    @include('portal-sso::button', ['class' => 'btn btn-primary w-100', 'label' => 'Masuk lewat Portal ASN'])
--}}
@if (app(\KominfoKabtang\PortalSso\PortalSsoClient::class)->enabled())
    <a href="{{ route('portal-sso.login') }}" class="{{ $class ?? 'btn btn-primary' }}">
        {{ $label ?? 'Masuk lewat Portal ASN' }}
    </a>
@endif
