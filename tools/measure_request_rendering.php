<?php
declare(strict_types=1);

/**
 * Compare the two module-loading paths in the same warmed HTTP test fixture.
 * Timings are descriptive, with no pass/fail threshold.
 */
function measureRequestRendering(string $fixture, string $articleUrl, array $auth, callable $request): void
{
    $engineFile = $fixture . '/includes/engine.php';
    $routerFile = $fixture . '/router.php';
    $engine = file_get_contents($engineFile);
    $router = file_get_contents($routerFile);
    $marker = '// Application transmet la liste ordonnee deja chargee pour cette requete.';
    if ($engine === false || $router === false || !str_contains($engine, $marker)) {
        throw new RuntimeException('Comparaison des performances indisponible.');
    }

    // Equivalent to the 0.9.6 engine for this comparison.
    $baseline = str_replace($marker, '$modules = $repositories->modules()->allOrdered();', $engine);
    $metricsFile = $fixture . '/request-metrics.json';
    $probe = <<<'PROBE'
if (isset($_SERVER['HTTP_X_P3_TEST_METRICS'])) {
    register_shutdown_function(static function (): void {
        file_put_contents(__DIR__ . '/request-metrics.json', json_encode(memory_get_peak_usage(false)));
    });
}
PROBE;
    $scenarios = [
        'accueil' => ['/', []],
        'article' => [$articleUrl, []],
        'recherche' => ['/search.html?q=installation', []],
        'tableau de bord' => ['/Admin.html', $auth],
    ];
    try {
        file_put_contents($routerFile, '<?php ' . $probe . "\n" . substr($router, 6));
        foreach ($scenarios as $label => [$path, $headers]) {
            $samples = ['0.9.6' => [], '0.9.7' => []];
            $headers[] = 'DNT: 1';
            $headers[] = 'X-P3-Test-Metrics: 1';
            for ($run = 0; $run < 13; $run++) {
                $variants = ['0.9.6' => $baseline, '0.9.7' => $engine];
                if ($run % 2 === 1) {
                    $variants = array_reverse($variants, true);
                }
                foreach ($variants as $version => $contents) {
                    file_put_contents($engineFile, $contents);
                    if (is_file($metricsFile)) {
                        unlink($metricsFile);
                    }
                    $start = hrtime(true);
                    [$status] = $request($path, 'GET', '', $headers);
                    $duration = (hrtime(true) - $start) / 1_000_000;
                    $peak = is_file($metricsFile) ? json_decode(file_get_contents($metricsFile)) : null;
                    if ($status !== 200 || !is_int($peak) || $peak <= 0) {
                        throw new RuntimeException('Mesure HTTP impossible pour ' . $label . ' (' . $status . ').');
                    }
                    if ($run > 0) {
                        $samples[$version][] = [$duration, $peak];
                    }
                }
            }
            foreach ($samples as $version => $rows) {
                $times = array_column($rows, 0);
                $peaks = array_column($rows, 1);
                sort($times);
                sort($peaks);
                printf("P3 mesure | %s | %s | %.3f ms | %.1f Kio | n=%d\n",
                    $label, $version, ($times[5] + $times[6]) / 2, ($peaks[5] + $peaks[6]) / 2048, count($rows));
            }
        }
    } finally {
        file_put_contents($engineFile, $engine);
        file_put_contents($routerFile, $router);
    }
}
