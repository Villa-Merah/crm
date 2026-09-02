<?php

namespace App\Support;

use Illuminate\Support\Str;

class IndonesiaCityCoordinates
{
    /**
     * Koordinat pusat kota/kabupaten untuk visual agregat dashboard.
     * Nilai ini bukan koordinat alamat calon siswa.
     *
     * @var array<string, array{latitude: float, longitude: float}>
     */
    private const LOCATIONS = [
        'AMBON' => ['latitude' => -3.6954, 'longitude' => 128.1814],
        'BALIKPAPAN' => ['latitude' => -1.2379, 'longitude' => 116.8529],
        'BANDA ACEH' => ['latitude' => 5.5483, 'longitude' => 95.3238],
        'BANDAR LAMPUNG' => ['latitude' => -5.3971, 'longitude' => 105.2668],
        'BANDUNG' => ['latitude' => -6.9175, 'longitude' => 107.6191],
        'BANDUNG BARAT' => ['latitude' => -6.8652, 'longitude' => 107.4917],
        'BANJARMASIN' => ['latitude' => -3.3186, 'longitude' => 114.5944],
        'BANTEN' => ['latitude' => -6.5000, 'longitude' => 106.2500],
        'BATAM' => ['latitude' => 1.0456, 'longitude' => 104.0305],
        'BATU' => ['latitude' => -7.8711667, 'longitude' => 112.5269482],
        'BEKASI' => ['latitude' => -6.2383, 'longitude' => 106.9756],
        'BENGKULU' => ['latitude' => -3.7928, 'longitude' => 102.2608],
        'BOGOR' => ['latitude' => -6.5950, 'longitude' => 106.8166],
        'CIMAHI' => ['latitude' => -6.8722, 'longitude' => 107.5425],
        'CIREBON' => ['latitude' => -6.7320, 'longitude' => 108.5523],
        'DENPASAR' => ['latitude' => -8.6500, 'longitude' => 115.2167],
        'DEPOK' => ['latitude' => -6.4025, 'longitude' => 106.7942],
        'DUMAI' => ['latitude' => 1.6631967, 'longitude' => 101.4470369],
        'GORONTALO' => ['latitude' => 0.5435, 'longitude' => 123.0568],
        'JAKARTA' => ['latitude' => -6.2088, 'longitude' => 106.8456],
        'JAKARTA BARAT' => ['latitude' => -6.1683, 'longitude' => 106.7588],
        'JAKARTA PUSAT' => ['latitude' => -6.1865, 'longitude' => 106.8341],
        'JAKARTA SELATAN' => ['latitude' => -6.2615, 'longitude' => 106.8106],
        'JAKARTA TIMUR' => ['latitude' => -6.2250, 'longitude' => 106.9004],
        'JAKARTA UTARA' => ['latitude' => -6.1214, 'longitude' => 106.7741],
        'JAMBI' => ['latitude' => -1.6101, 'longitude' => 103.6131],
        'JAYAPURA' => ['latitude' => -2.5337, 'longitude' => 140.7181],
        'KEDIRI' => ['latitude' => -7.8480, 'longitude' => 112.0178],
        'KUPANG' => ['latitude' => -10.1772, 'longitude' => 123.6070],
        'LEMBATA' => ['latitude' => -8.4140, 'longitude' => 123.5523],
        'LEWOLEBA' => ['latitude' => -8.3723, 'longitude' => 123.4180],
        'LHOKSEUMAWE' => ['latitude' => 5.1789659, 'longitude' => 97.1480544],
        'MADIUN' => ['latitude' => -7.6298, 'longitude' => 111.5239],
        'MAGELANG' => ['latitude' => -7.4706, 'longitude' => 110.2177],
        'MAKASSAR' => ['latitude' => -5.1477, 'longitude' => 119.4327],
        'MALANG' => ['latitude' => -7.9666, 'longitude' => 112.6326],
        'MAMUJU' => ['latitude' => -2.6806, 'longitude' => 118.8867],
        'MANADO' => ['latitude' => 1.4748, 'longitude' => 124.8421],
        'MATARAM' => ['latitude' => -8.5833, 'longitude' => 116.1167],
        'MEDAN' => ['latitude' => 3.5952, 'longitude' => 98.6722],
        'METRO' => ['latitude' => -5.1078839, 'longitude' => 105.3078642],
        'MOJOKERTO' => ['latitude' => -7.5413377, 'longitude' => 112.5094975],
        'PADANG' => ['latitude' => -0.9471, 'longitude' => 100.4172],
        'PALANGKA RAYA' => ['latitude' => -2.2161, 'longitude' => 113.9137],
        'PALEMBANG' => ['latitude' => -2.9761, 'longitude' => 104.7754],
        'PALU' => ['latitude' => -0.9003, 'longitude' => 119.8779],
        'PARIAMAN' => ['latitude' => -0.6263889, 'longitude' => 100.1177778],
        'PEKANBARU' => ['latitude' => 0.5071, 'longitude' => 101.4478],
        'PONTIANAK' => ['latitude' => -0.0263, 'longitude' => 109.3425],
        'SAMARINDA' => ['latitude' => -0.5022, 'longitude' => 117.1536],
        'SEMARANG' => ['latitude' => -6.9667, 'longitude' => 110.4167],
        'SERANG' => ['latitude' => -6.1201, 'longitude' => 106.1503],
        'SLEMAN' => ['latitude' => -7.7325, 'longitude' => 110.4024],
        'SOLO' => ['latitude' => -7.5755, 'longitude' => 110.8243],
        'SUMATERA UTARA' => ['latitude' => 2.0000, 'longitude' => 99.0000],
        'SUMEDANG' => ['latitude' => -6.8586, 'longitude' => 107.9164],
        'SURABAYA' => ['latitude' => -7.2575, 'longitude' => 112.7521],
        'TANGERANG' => ['latitude' => -6.1783, 'longitude' => 106.6319],
        'TANGERANG SELATAN' => ['latitude' => -6.2886, 'longitude' => 106.7179],
        'TANJUNGBALAI' => ['latitude' => 2.9703419, 'longitude' => 99.8020903],
        'TASIKMALAYA' => ['latitude' => -7.3506, 'longitude' => 108.2172],
        'TEGAL' => ['latitude' => -6.8694, 'longitude' => 109.1402],
        'YOGYAKARTA' => ['latitude' => -7.7956, 'longitude' => 110.3695],
    ];

    private const ALIASES = [
        'DIY' => 'YOGYAKARTA',
        'DKI JAKARTA' => 'JAKARTA',
        'JAKBAR' => 'JAKARTA BARAT',
        'JAKPUS' => 'JAKARTA PUSAT',
        'JAKSEL' => 'JAKARTA SELATAN',
        'JAKTIM' => 'JAKARTA TIMUR',
        'JAKUT' => 'JAKARTA UTARA',
        'SURAKARTA' => 'SOLO',
        'SUMATRA UTARA' => 'SUMATERA UTARA',
        'NORTH SUMATRA' => 'SUMATERA UTARA',
        'TANGSEL' => 'TANGERANG SELATAN',
    ];

    /** @return array{latitude: float, longitude: float}|null */
    public static function find(?string $city): ?array
    {
        $normalized = self::normalize($city);

        if ($normalized === '') {
            return null;
        }

        $canonical = self::ALIASES[$normalized] ?? $normalized;

        return self::LOCATIONS[$canonical] ?? null;
    }

    public static function normalize(?string $city): string
    {
        $city = Str::upper(Str::ascii(Str::squish((string) $city)));
        $city = preg_replace('/^(KOTA|KABUPATEN|KAB\.?)(\s+)/', '', $city) ?? $city;

        return trim($city, " \t\n\r\0\x0B,.-");
    }
}
