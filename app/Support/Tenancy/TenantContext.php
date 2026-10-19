<?php

namespace App\Support\Tenancy;

use App\Models\Institution;
use App\Models\InstitutionUser;
use LogicException;

class TenantContext
{
    private ?Institution $institution = null;

    private ?InstitutionUser $membership = null;

    public function set(Institution $institution, InstitutionUser $membership): void
    {
        if ($membership->institution_id !== $institution->getKey()) {
            throw new LogicException('Tenant membership does not belong to the selected institution.');
        }

        $this->institution = $institution;
        $this->membership = $membership;
    }

    public function institution(): Institution
    {
        return $this->institution
            ?? throw new LogicException('No validated tenant context is active.');
    }

    public function membership(): InstitutionUser
    {
        return $this->membership
            ?? throw new LogicException('No validated tenant membership is active.');
    }
}
