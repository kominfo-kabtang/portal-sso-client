<?php

namespace KominfoKabtang\PortalSso\Contracts;

interface SettingsResolver
{
    public function enabled(): bool;

    public function required(): bool;
}
