<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadEntryChannel;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListLeadRequest;
use App\Http\Requests\Api\V1\StoreLeadRequest;
use App\Http\Resources\Api\V1\LeadResource;
use App\Models\ApiClient;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\SumberLead;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    private const RELATIONS = [
        'cabang:cabang_id,kode_cabang,nama',
        'sekolah:sekolah_id,nama_sekolah',
        'minatProgram:program_bimbel_id,nama_program',
        'sumberLeadMaster:sumber_lead_id,nama,kode,warna',
        'diserahkanKe:id,name',
    ];

    public function index(ListLeadRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $client = $this->client($request);

        $query = Lead::query()
            ->visibleTo($client->user)
            ->with(self::RELATIONS)
            ->when($client->cabang_id, fn (Builder $builder, int $branch): Builder => $builder->where('cabang_id', $branch))
            ->when(! $client->cabang_id && isset($validated['cabang_id']), fn (Builder $builder): Builder => $builder->where('cabang_id', $validated['cabang_id']))
            ->when($validated['status'] ?? null, fn (Builder $builder, string $status): Builder => $builder->where('status_lead', $status))
            ->when($validated['sumber_lead_id'] ?? null, fn (Builder $builder, int $source): Builder => $builder->where('sumber_lead_id', $source))
            ->when($validated['sumber'] ?? null, fn (Builder $builder, string $source): Builder => $builder->whereHas('sumberLeadMaster', fn (Builder $sourceQuery): Builder => $sourceQuery->where('nama', $source)))
            ->when($validated['search'] ?? null, function (Builder $builder, string $search): Builder {
                $phone = preg_replace('/\D+/', '', $search);

                return $builder->where(function (Builder $nested) use ($search, $phone): void {
                    $nested->where('nama', 'like', "%{$search}%")
                        ->orWhere('kota_asal', 'like', "%{$search}%")
                        ->orWhere('kelas_sekolah', 'like', "%{$search}%")
                        ->when($phone !== '', fn (Builder $phoneQuery): Builder => $phoneQuery->orWhere('no_wa_normalized', 'like', "%{$phone}%"));
                });
            })
            ->orderByDesc('tanggal_masuk')
            ->orderByDesc('lead_id');

        $paginator = $query->paginate($validated['per_page'] ?? 25)->withQueryString();

        return response()->json([
            'data' => LeadResource::collection($paginator->items())->resolve($request),
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

    public function show(Request $request, int $lead): JsonResponse
    {
        $client = $this->client($request);
        $record = Lead::query()
            ->visibleTo($client->user)
            ->with(self::RELATIONS)
            ->when($client->cabang_id, fn (Builder $builder, int $branch): Builder => $builder->where('cabang_id', $branch))
            ->find($lead);

        if (! $record) {
            return ApiResponse::error($request, 'not_found', 'Lead tidak ditemukan.', 404);
        }

        return response()->json([
            'data' => (new LeadResource($record))->resolve($request),
            'meta' => ['request_id' => $request->attributes->get('api_request_id')],
        ]);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $client = $this->client($request);
        $data = $request->validated();

        if ($client->cabang_id && isset($data['cabang_id']) && (int) $data['cabang_id'] !== (int) $client->cabang_id) {
            return ApiResponse::error($request, 'branch_scope_violation', 'API client tidak dapat menulis ke cabang lain.', 403);
        }

        $branchId = $client->cabang_id ?: ($data['cabang_id'] ?? null);
        $sourceId = $data['sumber_lead_id']
            ?? (isset($data['sumber_lead']) ? SumberLead::findActiveByNameOrCode($data['sumber_lead'])?->getKey() : null);

        if ($branchId && ! Cabang::query()->whereKey($branchId)->where('aktif', true)->exists()) {
            return ApiResponse::error($request, 'invalid_branch', 'Cabang yang dipilih tidak aktif atau tidak ditemukan.', 422, ['cabang_id' => ['Cabang tidak valid.']]);
        }

        if (isset($data['diserahkan_ke_user_id'])) {
            $picValid = User::query()->whereKey($data['diserahkan_ke_user_id'])->where('aktif', true)
                ->when($branchId, fn (Builder $query): Builder => $query->where('cabang_id', $branchId))
                ->exists();
            if (! $picValid) {
                return ApiResponse::error($request, 'invalid_pic', 'PIC harus aktif dan berada pada cabang lead.', 422, ['diserahkan_ke_user_id' => ['PIC tidak valid untuk cabang ini.']]);
            }
        }

        if ($client->user->hasRole(UserRole::Staff)) {
            if (isset($data['diserahkan_ke_user_id']) && (int) $data['diserahkan_ke_user_id'] !== (int) $client->user_id) {
                return ApiResponse::error($request, 'pic_scope_violation', 'Staff hanya dapat menetapkan dirinya sendiri sebagai PIC.', 403);
            }
            $data['diserahkan_ke_user_id'] = $client->user_id;
        }

        $lead = DB::transaction(fn (): Lead => Lead::create([
            'nama' => $data['nama'],
            'no_wa' => $data['no_wa'],
            'kota_asal' => $data['kota_asal'] ?? null,
            'kelas_sekolah' => $data['kelas_sekolah'] ?? null,
            'sekolah_id' => $data['sekolah_id'] ?? null,
            'minat_program_id' => $data['minat_program_id'] ?? null,
            'sumber_lead_id' => $sourceId,
            'cabang_id' => $branchId,
            'status_lead' => $data['status_lead'] ?? LeadStatus::Cold->value,
            'diinput_oleh_user_id' => $client->user_id,
            'diserahkan_ke_user_id' => $data['diserahkan_ke_user_id'] ?? null,
            'tanggal_masuk' => $data['tanggal_masuk'] ?? now()->toDateString(),
            'kanal_masuk' => LeadEntryChannel::Manual->value,
        ]));
        $lead->load(self::RELATIONS);

        return response()->json([
            'data' => (new LeadResource($lead))->resolve($request),
            'meta' => ['request_id' => $request->attributes->get('api_request_id')],
        ], 201);
    }

    private function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }
}
