<?php

namespace KominfoKabtang\PortalSso\Resolvers;

use KominfoKabtang\PortalSso\Contracts\SettingsResolver;

class ConfigSettingsResolver implements SettingsResolver
{
    public function enabled(): bool
    {
        return (bool) config('portal-sso.enabled', false);
    }

    public function required(): bool
    {
        // SSO wajib tidak berarti apa-apa bila SSO sendiri nonaktif.
        return $this->enabled() && (bool) config('portal-sso.required', false);
    }
}
