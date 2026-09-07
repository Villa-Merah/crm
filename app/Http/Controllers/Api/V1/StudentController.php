<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListStudentRequest;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Pendaftaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    public function index(ListStudentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $client = $request->attributes->get('api_client');

        $query = Pendaftaran::query()
            ->visibleTo($client->user)
            ->with([
                'lead:lead_id,nama,no_wa,kota_asal,kelas_sekolah,sekolah_id',
                'lead.sekolah:sekolah_id,nama_sekolah',
                'programBimbel:program_bimbel_id,nama_program,biaya_pendidikan',
                'cabang:cabang_id,kode_cabang,nama',
            ])
            ->withSum([
                'payments as paid_total' => fn (Builder $builder): Builder => $builder
                    ->whereIn('status_pembayaran', ['Lunas', 'Terverifikasi']),
            ], 'nominal')
            ->when($client->cabang_id, fn (Builder $builder, int $branch): Builder => $builder->where('cabang_id', $branch))
            ->when($validated['status'] ?? null, fn (Builder $builder, string $status): Builder => $builder->where('status_pendaftaran', $status))
            ->when(! $client->cabang_id && isset($validated['cabang_id']), fn (Builder $builder): Builder => $builder->where('cabang_id', $validated['cabang_id']))
            ->when($validated['search'] ?? null, function (Builder $builder, string $search): Builder {
                return $builder->whereHas('lead', fn (Builder $lead): Builder => $lead
                    ->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_wa', 'like', "%{$search}%")
                    ->orWhere('kota_asal', 'like', "%{$search}%"));
            })
            ->orderBy('pendaftaran_id');

        $paginator = $query->paginate($validated['per_page'] ?? 100)->withQueryString();

        return response()->json([
            'data' => StudentResource::collection($paginator->items())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'request_id' => $request->attributes->get('api_request_id'),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
