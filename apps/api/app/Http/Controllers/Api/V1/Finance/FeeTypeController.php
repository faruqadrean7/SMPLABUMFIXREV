<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\FeeType;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeeTypeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = FeeType::query();

        if ($request->has('category') && $request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $feeTypes = $query->orderBy('name')->get();

        return $this->successResponse($feeTypes);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:fee_types,code'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:spp,gedung,seragam,kegiatan,lainnya'],
            'default_amount' => ['required', 'integer', 'min:0'],
            'is_recurring' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $feeType = FeeType::create([
            'id' => (string) Str::uuid(),
            'school_id' => $request->user()?->school_id,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'category' => $validated['category'],
            'default_amount' => (int) $validated['default_amount'],
            'is_recurring' => $validated['is_recurring'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'description' => $validated['description'] ?? null,
        ]);

        return $this->successResponse($feeType, 201);
    }

    public function show(string $id): JsonResponse
    {
        $feeType = FeeType::findOrFail($id);
        return $this->successResponse($feeType);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $feeType = FeeType::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:fee_types,code,' . $feeType->id],
            'name' => ['sometimes', 'string', 'max:150'],
            'category' => ['sometimes', 'in:spp,gedung,seragam,kegiatan,lainnya'],
            'default_amount' => ['sometimes', 'integer', 'min:0'],
            'is_recurring' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $feeType->update($validated);

        return $this->successResponse($feeType);
    }

    public function destroy(string $id): JsonResponse
    {
        $feeType = FeeType::findOrFail($id);

        if ($feeType->invoiceItems()->exists()) {
            return $this->errorResponse(
                'Jenis biaya ini telah digunakan dalam tagihan siswa dan tidak dapat dihapus. Anda dapat menonaktifkannya.',
                'CONFLICT',
                409
            );
        }

        $feeType->delete();

        return $this->successResponse(['message' => 'Jenis biaya berhasil dihapus.']);
    }
}
