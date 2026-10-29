<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentProviderSettingController extends Controller
{
    /**
     * Daftar penyedia pembayaran pihak ketiga & instruksi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentProviderSetting::query()->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Tambah konfigurasi penyedia pembayaran baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'instructions' => ['required', 'string'],
            'info_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $setting = PaymentProviderSetting::create([
            'name' => $validated['name'],
            'instructions' => $validated['instructions'],
            'info_url' => $validated['info_url'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi penyedia pembayaran berhasil dibuat.',
            'data' => $setting,
        ], 201);
    }

    /**
     * Detail konfigurasi penyedia pembayaran.
     */
    public function show(string $id): JsonResponse
    {
        $setting = PaymentProviderSetting::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    /**
     * Perbarui konfigurasi penyedia pembayaran.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $setting = PaymentProviderSetting::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'instructions' => ['sometimes', 'required', 'string'],
            'info_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $setting->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi penyedia pembayaran berhasil diperbarui.',
            'data' => $setting->fresh(),
        ]);
    }

    /**
     * Hapus konfigurasi penyedia pembayaran.
     */
    public function destroy(string $id): JsonResponse
    {
        $setting = PaymentProviderSetting::findOrFail($id);
        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi penyedia pembayaran berhasil dihapus.',
        ]);
    }
}
