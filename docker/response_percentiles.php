<?php

declare(strict_types=1);

$samples = [];

while (($line = fgets(STDIN)) !== false) {
    if (preg_match('/"request_time":\s*([0-9]+(?:\.[0-9]+)?)/', $line, $matches) !== 1) {
        continue;
    }

    $samples[] = (float) $matches[1] * 1000;
}

if ($samples === []) {
    fwrite(STDERR, "Tidak ada sampel request_time pada input.\n");
    exit(1);
}

sort($samples, SORT_NUMERIC);

$percentile = static function (array $values, float $fraction): float {
    $index = (int) ceil(count($values) * $fraction) - 1;

    return $values[max(0, min($index, count($values) - 1))];
};

$count = count($samples);
$average = array_sum($samples) / $count;

printf(
    "samples=%d min=%.1fms avg=%.1fms p50=%.1fms p95=%.1fms max=%.1fms\n",
    $count,
    $samples[0],
    $average,
    $percentile($samples, 0.50),
    $percentile($samples, 0.95),
    $samples[$count - 1],
);
