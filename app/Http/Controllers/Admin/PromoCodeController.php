<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PromoType;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Services\PromoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Codes promo : création, modification, activation. Un code utilisé ne se supprime pas (il se
 * désactive) : l'historique des commandes garde son code et sa réduction.
 */
class PromoCodeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/PromoCodes/Index', [
            'codes' => PromoCode::query()
                ->withCount(['redemptions as uses' => fn ($query) => $query->whereHas('order', fn ($q) => $q->whereNotIn('status', [OrderStatus::Refused, OrderStatus::Cancelled]))])
                ->withSum('redemptions as total_discount', 'discount')
                ->latest('id')
                ->get()
                ->map(fn (PromoCode $promo) => [
                    'id' => $promo->id,
                    'code' => $promo->code,
                    'description' => $promo->description,
                    'type' => $promo->type->value,
                    'value' => $promo->value,
                    'label' => $promo->label(),
                    'min_subtotal' => $promo->min_subtotal,
                    'max_uses' => $promo->max_uses,
                    'max_uses_per_user' => $promo->max_uses_per_user,
                    'first_order_only' => $promo->first_order_only,
                    'starts_at' => $promo->starts_at?->setTimezone(config('gogab.timezone'))->format('Y-m-d'),
                    'ends_at' => $promo->ends_at?->setTimezone(config('gogab.timezone'))->format('Y-m-d'),
                    'is_active' => $promo->is_active,
                    'expired' => (bool) $promo->ends_at?->isPast(),
                    'uses' => (int) $promo->uses,
                    'total_discount' => (int) $promo->total_discount,
                ]),
            'types' => collect(PromoType::cases())->map(fn (PromoType $type) => ['value' => $type->value, 'label' => $type->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $promo = PromoCode::create($this->validated($request));

        return back()->with('success', "Code {$promo->code} créé.");
    }

    public function update(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update($this->validated($request, $promoCode));

        return back()->with('success', "Code {$promoCode->code} mis à jour.");
    }

    /** Active ou désactive un code sans le modifier. */
    public function toggle(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update(['is_active' => ! $promoCode->is_active]);

        return back()->with('success', $promoCode->is_active ? "Code {$promoCode->code} activé." : "Code {$promoCode->code} désactivé.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        $request->merge(['code' => PromoService::normalize($request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:30', Rule::unique('promo_codes', 'code')->ignore($promo?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'type' => ['required', Rule::enum(PromoType::class)],
            'value' => ['exclude_if:type,'.PromoType::FreeDelivery->value, 'required', 'integer', 'min:1', $request->input('type') === PromoType::Percent->value ? 'max:100' : 'max:1000000'],
            'min_subtotal' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_uses_per_user' => ['required', 'integer', 'min:1', 'max:1000'],
            'first_order_only' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ], [
            'code.required' => 'Indiquez le code.',
            'code.min' => 'Le code doit faire 3 caractères minimum.',
            'code.unique' => 'Ce code existe déjà.',
            'value.required' => 'Indiquez la valeur de la réduction.',
            'value.max' => 'Un pourcentage ne peut pas dépasser 100.',
            'ends_at.after_or_equal' => 'La fin ne peut pas précéder le début.',
        ]);

        $tz = config('gogab.timezone');

        return [
            ...$data,
            'value' => $data['type'] === PromoType::FreeDelivery->value ? 0 : (int) $data['value'],
            'min_subtotal' => (int) ($data['min_subtotal'] ?? 0),
            // Jours de Libreville : début à 00:00, fin à 23:59:59, stockés dans le fuseau de l'application (UTC).
            'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at'], $tz)->startOfDay()->setTimezone(config('app.timezone')) : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at'], $tz)->endOfDay()->setTimezone(config('app.timezone')) : null,
        ];
    }
}
