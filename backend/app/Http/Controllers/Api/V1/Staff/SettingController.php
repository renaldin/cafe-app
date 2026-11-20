<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Payments\GatewayManager;
use App\Services\ImageService;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show()
    {
        $s = Settings::all();
        $s['cafe_logo_url'] = ImageService::url($s['cafe_logo']);
        $s['payment_qris_image_url'] = ImageService::url($s['payment_qris_image']);
        $gw = GatewayManager::active();

        return $this->ok($s, null, [
            'gateway' => $gw?->name(),
            'webhook_url' => $gw ? url('/api/v1/public/webhooks/'.$gw->name()) : null,
            'timezone' => config('app.timezone'),
            'php_version' => PHP_VERSION,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cafe_name' => ['sometimes', 'string', 'max:100'],
            'cafe_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cafe_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'cafe_email' => ['sometimes', 'nullable', 'email'],
            'social_instagram' => ['sometimes', 'nullable', 'string', 'max:100'],
            'social_tiktok' => ['sometimes', 'nullable', 'string', 'max:100'],
            'social_website' => ['sometimes', 'nullable', 'string', 'max:150'],
            'opening_hours' => ['sometimes', 'array'],
            'opening_hours.*.open' => ['required_with:opening_hours', 'date_format:H:i'],
            'opening_hours.*.close' => ['required_with:opening_hours', 'date_format:H:i'],
            'opening_hours.*.closed' => ['boolean'],
            'enforce_hours' => ['sometimes', 'boolean'],
            'store_closed' => ['sometimes', 'boolean'],
            'store_closed_message' => ['sometimes', 'nullable', 'string', 'max:200'],
            'tax_enabled' => ['sometimes', 'boolean'],
            'tax_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'service_enabled' => ['sometimes', 'boolean'],
            'service_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'rounding' => ['sometimes', 'integer', 'in:0,100,500,1000'],
            'rounding_mode' => ['sometimes', 'in:nearest,up,down'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:300'],
            'receipt_paper' => ['sometimes', 'in:58,80'],
            'invoice_prefix' => ['sometimes', 'string', 'max:6', 'alpha_num'],
            'require_confirmation' => ['sometimes', 'boolean'],
            'stock_deduct_on' => ['sometimes', 'in:paid,served'],
            'auto_soldout' => ['sometimes', 'boolean'],
            'loyalty_enabled' => ['sometimes', 'boolean'],
            'loyalty_spend_per_point' => ['sometimes', 'integer', 'min:1000'],
            'loyalty_point_value' => ['sometimes', 'integer', 'min:1'],
            'loyalty_min_redeem' => ['sometimes', 'integer', 'min:0'],
            'max_qty_per_item' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'max_lines_per_order' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'kds_warn_minutes' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'kds_danger_minutes' => ['sometimes', 'integer', 'min:1', 'max:240'],
            'payment_bank_info' => ['sometimes', 'nullable', 'string', 'max:300'],
            'allow_online_payment' => ['sometimes', 'boolean'],
            'takeaway_enabled' => ['sometimes', 'boolean'],
            'delivery_enabled' => ['sometimes', 'boolean'],
            'delivery_fee' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'delivery_min_order' => ['sometimes', 'integer', 'min:0'],
            'delivery_area_note' => ['sometimes', 'nullable', 'string', 'max:300'],
            'preorder_enabled' => ['sometimes', 'boolean'],
            'preorder_lead_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'preorder_max_days' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'online_pay_first' => ['sometimes', 'boolean'],
        ]);
        $before = array_intersect_key(Settings::all(), $data);
        Settings::set($data);
        Activity::log('setting.update', null, 'Ubah pengaturan', $before, $data);

        return $this->show();
    }

    public function upload(Request $request, ImageService $images)
    {
        $data = $request->validate([
            'key' => ['required', 'in:cafe_logo,payment_qris_image'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $old = Settings::get($data['key']);
        $path = $images->store($request->file('image'), 'settings', $data['key'] === 'payment_qris_image' ? 1000 : 400, 90);
        Settings::set([$data['key'] => $path]);
        $images->delete($old);

        return $this->ok(['url' => ImageService::url($path)], 'Gambar diperbarui.');
    }
}
