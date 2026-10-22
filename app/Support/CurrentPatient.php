<?php

namespace App\Support;

use App\Models\Patient;

class CurrentPatient
{
    public function patient(): Patient
    {
        return Patient::query()->findOrFail(1);
    }
}
