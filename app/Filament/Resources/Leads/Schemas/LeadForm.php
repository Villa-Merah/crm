<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\SchoolNamePrefix;
use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\Schools\CreateSchoolFromLead;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema->components([
            Section::make('Data calon siswa')
                ->description('Informasi utama untuk menghubungi dan mengenali calon siswa.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('nama')->label('Nama lengkap')->required()->maxLength(150),
                    TextInput::make('no_wa')->label('Nomor WhatsApp')->tel()->required()->maxLength(20)->placeholder('08xxxxxxxxxx'),
                    TextInput::make('kota_asal')->label('Kota asal')->required()->maxLength(100)->placeholder('Contoh: Bandung'),
                    Select::make('sekolah_id')
                        ->label('Sekolah')
                        ->options(fn (): array => Sekolah::query()
                            ->where('aktif', true)
                            ->orderBy('nama_sekolah')
                            ->pluck('nama_sekolah', 'sekolah_id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->helperText('Tidak ditemukan? Klik tombol + untuk menambahkan sekolah baru.')
                        ->createOptionModalHeading('Tambahkan sekolah baru')
                        ->createOptionAction(fn (Action $action): Action => $action
                            ->label('Tambah sekolah')
                            ->tooltip('Tambah sekolah yang belum tersedia'))
                        ->createOptionForm([
                            Select::make('prefix')
                                ->label('Format sekolah')
                                ->options(SchoolNamePrefix::options())
                                ->live()
                                ->required(),
                            TextInput::make('nama_sekolah_baru')
                                ->label('Nama atau nomor sekolah')
                                ->placeholder('Contoh: 3 Bandung')
                                ->helperText('Awalan dipasang otomatis. Hasil contoh: SMAN 3 BANDUNG.')
                                ->visible(fn (Get $get): bool => $get('prefix') !== SchoolNamePrefix::AlumniGapyear->value)
                                ->required(fn (Get $get): bool => $get('prefix') !== SchoolNamePrefix::AlumniGapyear->value)
                                ->maxLength(190),
                            TextInput::make('npsn')
                                ->label('NPSN')
                                ->helperText('Opsional. Jika diisi, gunakan 8 digit.')
                                ->visible(fn (Get $get): bool => $get('prefix') !== SchoolNamePrefix::AlumniGapyear->value)
                                ->length(8)
                                ->regex('/^\d{8}$/')
                                ->unique(Sekolah::class, 'npsn'),
                            TextInput::make('kota')
                                ->label('Kota/Kabupaten')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('provinsi')
                                ->label('Provinsi')
                                ->maxLength(100),
                        ])
                        ->createOptionUsing(fn (array $data): int => app(CreateSchoolFromLead::class)->create($data)->getKey()),
                    Select::make('minat_program_id')->label('Program yang diminati')->options(ProgramBimbel::query()->where('aktif', true)->orderBy('nama_program')->pluck('nama_program', 'program_bimbel_id'))->searchable()->preload(),
                ]),
            Section::make('Penanganan lead')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('cabang_id')->label('Cabang')->options(Cabang::query()->where('aktif', true)->when(! $user->hasGlobalBranchAccess(), fn ($q) => $q->whereKey($user->cabang_id))->orderBy('nama')->pluck('nama', 'cabang_id'))->default($user->cabang_id)->required()->disabled(! $user->hasGlobalBranchAccess())->dehydrated(),
                    Select::make('diserahkan_ke_user_id')->label('PIC')->options(User::query()->where('aktif', true)->when(! $user->hasGlobalBranchAccess(), fn ($q) => $q->where('cabang_id', $user->cabang_id))->whereIn('role', [UserRole::Staff->value, UserRole::AdminCabang->value])->orderBy('name')->pluck('name', 'id'))->default($user->hasRole(UserRole::Staff) ? $user->id : null)->disabled($user->hasRole(UserRole::Staff))->dehydrated()->searchable()->required(),
                    Select::make('sumber_lead')->label('Sumber leads')->options(LeadSource::options())->required(),
                    Select::make('status_lead')->label('Status')->options(LeadStatus::options())->default(LeadStatus::Cold->value)->required(),
                    DatePicker::make('tanggal_masuk')->label('Tanggal masuk')->default(today())->maxDate(today())->required(),
                    Hidden::make('diinput_oleh_user_id')->default($user->id),
                ]),
        ]);
    }
}
