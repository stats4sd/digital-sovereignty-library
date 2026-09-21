<?php

namespace App\Support\Curriculum;

/**
 * Lays the learning-map nodes out for any number of modules, in a 0–100 × 0–100 coordinate
 * space that the map partial renders as percentages of its container (and as the SVG viewBox
 * with preserveAspectRatio="none", so the same numbers position both the dots and the trail).
 *
 * Nodes are spread evenly across the width and cycle through an upper and a lower row (a middle
 * row joins in above ROWS_TWO_MAX nodes) so labels that share a row stay far enough apart; the
 * trail is a smooth curve through them, ending in a tail that points at the "Build your
 * toolkit" button centred below the map.
 */
class MapLayout
{
    public const LEFT = 12.0;

    public const RIGHT = 88.0;

    public const TOP = 14.0;

    public const BOTTOM = 56.0;

    public const MIDDLE = 35.0;

    public const ROWS_TWO_MAX = 7;

    /**
     * @return list<array{x: float, y: float}>
     */
    public static function nodes(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return [['x' => 50.0, 'y' => self::MIDDLE]];
        }

        $step = (self::RIGHT - self::LEFT) / ($count - 1);
        $rows = $count > self::ROWS_TWO_MAX
            ? [self::TOP, self::MIDDLE, self::BOTTOM]
            : [self::TOP, self::BOTTOM];

        return collect(range(0, $count - 1))
            ->map(fn (int $index): array => [
                'x' => round(self::LEFT + $index * $step, 2),
                'y' => $rows[$index % count($rows)],
            ])
            ->all();
    }

    /**
     * SVG path data for the trail through the given nodes plus the tail to (50, 100).
     *
     * @param  list<array{x: float, y: float}>  $nodes
     */
    public static function trail(array $nodes): string
    {
        if ($nodes === []) {
            return '';
        }

        $first = $nodes[0];
        $segments = ["M{$first['x']},{$first['y']}"];

        for ($index = 1; $index < count($nodes); $index++) {
            $from = $nodes[$index - 1];
            $to = $nodes[$index];
            $midX = round(($from['x'] + $to['x']) / 2, 2);

            $segments[] = "C{$midX},{$from['y']} {$midX},{$to['y']} {$to['x']},{$to['y']}";
        }

        $last = $nodes[count($nodes) - 1];
        $bendX = round(min($last['x'] + 10, 98), 2);
        $bendY = round(($last['y'] + 100) / 2, 2);

        $segments[] = "C{$bendX},{$bendY} 62,96 50,100";

        return implode(' ', $segments);
    }
}
