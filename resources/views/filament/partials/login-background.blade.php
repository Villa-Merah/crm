@php
    $backgroundPath = \App\Models\CrmSetting::getValue('login_background_image');
    $backgroundUrl = $backgroundPath
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($backgroundPath)
        : null;

    if ($backgroundUrl && $backgroundPath) {
        $backgroundFile = storage_path('app/public/'.ltrim($backgroundPath, '/'));
        $backgroundVersion = file_exists($backgroundFile) ? filemtime($backgroundFile) : now()->timestamp;
        $backgroundUrl .= '?v=' . $backgroundVersion;
    }
@endphp

@if ($backgroundUrl)
    <style>
        body .fi-simple-layout {
            background-image:
                linear-gradient(90deg, rgb(8 34 49 / 82%) 0%, rgb(8 34 49 / 62%) 36%, rgb(8 34 49 / 18%) 72%),
                linear-gradient(90deg, rgb(255 255 255 / 6%) 50%, transparent 50%),
                linear-gradient(180deg, rgb(255 255 255 / 6%) 50%, transparent 50%),
                url('{{ $backgroundUrl }}') !important;
            background-position: center, 0 0, 0 0, center;
            background-size: cover, 44px 44px, 44px 44px, cover;
            background-repeat: no-repeat, repeat, repeat, no-repeat;
        }

        @media (max-width: 700px) {
            body .fi-simple-layout {
                background-image:
                    linear-gradient(180deg, rgb(8 34 49 / 62%), rgb(8 34 49 / 86%)),
                    linear-gradient(90deg, rgb(255 255 255 / 6%) 50%, transparent 50%),
                    linear-gradient(180deg, rgb(255 255 255 / 6%) 50%, transparent 50%),
                    url('{{ $backgroundUrl }}') !important;
                background-size: cover, 32px 32px, 32px 32px, cover;
            }
        }
    </style>
@endif
