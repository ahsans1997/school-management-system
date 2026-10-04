<?php

namespace App\Http\Requests\Guardian;

class UpdateGuardianRequest extends GuardianRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }
}
