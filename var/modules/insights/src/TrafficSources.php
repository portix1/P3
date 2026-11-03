<?php
declare(strict_types=1);

namespace Portix\Insights;

use PDO;

final class TrafficSources
{
    private static function host(string $value): string
    {
        $host = strtolower((string) parse_url('http://' . $value, PHP_URL_HOST));
        return preg_replace('/^www\./', '', rtrim($host, '.')) ?? $host;
    }

    public static function classify(string $referrer, string $siteHost): string
    {
        if ($referrer === 'internal' || (self::host($referrer) !== '' && self::host($referrer) === self::host($siteHost))) {
            return 'internal';
        }
        return $referrer;
    }

    /** Group before limiting, so historical www/bare-host rows remain accurate. */
    public static function ranking(PDO $pdo, int $days, string $siteHost): array
    {
        $q = $pdo->prepare('SELECT host,SUM(views) AS views FROM analytics_referrers WHERE day >= :cutoff GROUP BY host');
        $q->execute(['cutoff' => gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'))]);
        $groups = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $host = self::classify((string) $row['host'], $siteHost);
            $groups[$host] = ($groups[$host] ?? 0) + (int) $row['views'];
        }
        $rows = [];
        foreach ($groups as $host => $views) { $rows[] = ['host' => $host, 'views' => $views]; }
        usort($rows, static fn (array $a, array $b): int => ($b['views'] <=> $a['views']) ?: strcmp($a['host'], $b['host']));
        return array_slice($rows, 0, 10);
    }
}
