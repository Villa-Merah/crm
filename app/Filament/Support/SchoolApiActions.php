<?php

namespace App\Filament\Support;

use App\Services\Schools\SchoolApiClient;
use App\Services\Schools\SyncSchoolFromApi;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;

class SchoolApiActions
{
    public static function fillSchoolForm(): Action
    {
        return static::baseAction()
            ->action(function (array $data, Set $set): void {
                $school = app(SchoolApiClient::class)->findByNpsn((string) $data['api_school_npsn']);

                if (! $school) {
                    static::failureNotification();

                    return;
                }

                foreach ($school as $field => $value) {
                    $set($field, $value);
                }

                $set('sumber_data', 'api-sekolah');
                $set('api_last_synced_at', now()->toDateTimeString());

                Notification::make()->success()->title('Data sekolah berhasil diambil')->body('Periksa kembali data, lalu klik Simpan.')->send();
            });
    }

    public static function selectSchoolForLead(): Action
    {
        return static::baseAction()
            ->action(function (array $data, Set $set): void {
                $schoolData = app(SchoolApiClient::class)->findByNpsn((string) $data['api_school_npsn']);

                if (! $schoolData) {
                    static::failureNotification();

                    return;
                }

                $school = app(SyncSchoolFromApi::class)->sync($schoolData);
                $set('sekolah_id', $school->getKey());

                Notification::make()->success()->title('Sekolah dipilih')->body($school->nama_sekolah.' telah disimpan pada master sekolah.')->send();
            });
    }

    private static function baseAction(): Action
    {
        return Action::make('cariSekolahApi')
            ->label('Cari dari API Sekolah Indonesia')
            ->icon('heroicon-o-magnifying-glass')
            ->color('info')
            ->visible(fn (): bool => (bool) config('services.school_api.enabled'))
            ->modalHeading('Cari sekolah dari API Sekolah Indonesia')
            ->modalDescription('Ketik minimal 3 karakter nama sekolah atau NPSN, lalu pilih hasil yang sesuai.')
            ->modalSubmitActionLabel('Gunakan data sekolah')
            ->schema([
                Select::make('api_school_npsn')
                    ->label('Sekolah')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => app(SchoolApiClient::class)->searchOptions($search))
                    ->getOptionLabelUsing(function ($value): ?string {
                        $client = app(SchoolApiClient::class);
                        $school = $client->findByNpsn((string) $value);

                        return $school ? $client->formatLabel($school) : (string) $value;
                    })
                    ->searchPrompt('Ketik minimal 3 karakter nama sekolah atau NPSN')
                    ->noSearchResultsMessage('Sekolah tidak ditemukan atau API sedang tidak tersedia.')
                    ->required(),
            ]);
    }

    private static function failureNotification(): void
    {
        Notification::make()
            ->danger()
            ->title('Data sekolah tidak dapat diambil')
            ->body('Coba kembali beberapa saat lagi atau gunakan input manual.')
            ->send();
    }
}
