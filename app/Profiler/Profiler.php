<?php

namespace App\Profiler;
class Profiler
{
    private static array $metrics = [];

    public static function profileRequest(string $label, callable $callable, array $tags = [])
    {
        $start = microtime(true);
        $result = $callable();

        $end = microtime(true);
        $duration = $end - $start;

        self::$metrics[$label] = [
            'duration' => $duration,
            'tags' => $tags,
            'date' => (new \DateTime())->format(DATE_ATOM),
        ];

        return $result;
    }

    public static function report(int $limit = 5): array
    {
        $sorted = self::$metrics;
        uasort($sorted, fn($a, $b) => $b['duration'] <=> $a['duration']);
        return array_slice($sorted, 0, $limit, true);
    }

    public static function getAll(): array
    {
        return self::$metrics;
    }
}
