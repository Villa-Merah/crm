<?php

namespace Tests\Unit;

use App\Support\IndonesiaCityCoordinates;
use PHPUnit\Framework\TestCase;

class IndonesiaCityCoordinatesTest extends TestCase
{
    public function test_it_normalizes_city_prefixes_and_common_aliases(): void
    {
        $this->assertSame(
            IndonesiaCityCoordinates::find('Jakarta Selatan'),
            IndonesiaCityCoordinates::find('Kota JakSel'),
        );
        $this->assertSame(
            IndonesiaCityCoordinates::find('Mamuju'),
            IndonesiaCityCoordinates::find('Kab. Mamuju'),
        );
    }

    public function test_it_returns_null_for_unknown_or_empty_locations(): void
    {
        $this->assertNull(IndonesiaCityCoordinates::find('Wilayah yang belum terdaftar'));
        $this->assertNull(IndonesiaCityCoordinates::find(null));
    }

    public function test_all_current_dashboard_cities_have_coordinates(): void
    {
        $cities = [
            'Tanjungbalai',
            'Palembang',
            'Mojokerto',
            'Serang',
            'Metro',
            'Pariaman',
            'Dumai',
            'Banjarmasin',
            'Batu',
            'Bengkulu',
            'Lhokseumawe',
        ];

        foreach ($cities as $city) {
            $this->assertNotNull(IndonesiaCityCoordinates::find($city), $city.' belum mempunyai koordinat.');
        }
    }

    public function test_it_maps_new_production_regions_and_spelling_variants(): void
    {
        $locations = [
            'Banten',
            'Kabupaten Lembata',
            'Lewoleba',
            'Sumedang',
            'Sumatera Utara',
            'Sumatra Utara',
            'North Sumatra',
        ];

        foreach ($locations as $location) {
            $this->assertNotNull(
                IndonesiaCityCoordinates::find($location),
                $location.' belum mempunyai koordinat.',
            );
        }

        $this->assertSame(
            IndonesiaCityCoordinates::find('Sumatera Utara'),
            IndonesiaCityCoordinates::find('Sumatra Utara'),
        );
    }
}
