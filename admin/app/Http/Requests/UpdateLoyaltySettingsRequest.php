<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Only admin edits loyalty rules — route already requires role:admin. */
class UpdateLoyaltySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'earn_rate_pesos' => ['required', 'integer', 'min:1'],
            'redeem_points_per_peso' => ['required', 'integer', 'min:1'],
            'max_redeem_per_order' => ['required', 'integer', 'min:0'],
            'expiry_months' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'earn_rate_pesos.min' => 'Earn rate must be at least PHP 1 per point.',
            'redeem_points_per_peso.min' => 'Redeem rate must be at least 1 point per peso.',
            'max_redeem_per_order.min' => 'Max redeem per order cannot be negative.',
            'expiry_months.min' => 'Expiry must be at least 1 month.',
        ];
    }
}
