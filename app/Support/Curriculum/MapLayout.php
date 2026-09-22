<?php

namespace App\Support\Curriculum;

/**
 * Lays the learning-map nodes out along a fixed, hand-drawn trail for any number of modules.
 *
 * Everything is in the 0–100 × 0–100 space the map partial uses both for the nodes' `left/top`
 * percentages and for the SVG viewBox (rendered with preserveAspectRatio="none"), so the same
 * numbers position the dots and draw the trail.
 *
 * The trail itself never changes: it is the original curve (two rows joined by a bend on the
 * right, then a tail curling off the bottom-left towards the "Build your toolkit" button). Nodes
 * are spread at equal arc-length intervals along the two rows, so adding or removing a module
 * reshuffles the remaining nodes evenly along the same route. The bend joining the rows is a
 * connector that carries no nodes: a label there would overflow the right edge of the map, and
 * skipping it is what keeps the seeded five modules where the hand-placed originals were.
 *
 * Arc length is measured in the map's rendered aspect ratio (roughly 3:1), not in raw viewBox
 * units, otherwise the vertical bends would count for far less than they look and the spacing
 * would appear bunched on screen.
 */
class MapLayout
{
    /** Rendered width ÷ height of the map container (max-w-4xl × 300px). */
    public const ASPECT = 3.0;

    /** Straight-line samples per cubic segment when measuring arc length. */
    private const SAMPLES_PER_SEGMENT = 64;

    /**
     * Cubic Bézier segments of the trail: [x1, y1, x2, y2, x, y] following the start point.
     * Segments 0–1 are the top row, 2 the connecting bend, 3–4 the bottom row, 5–6 the tail.
     *
     * @var list<array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}>
     */
    private const SEGMENTS = [
        [23, 7, 35, 7, 46, 12],
        [57, 7, 69, 7, 80, 12],
        [94, 14, 98, 42, 88, 53],
        [78, 55, 70, 55, 60, 55],
        [48, 55, 38, 55, 26, 55],
        [10, 55, 4, 72, 16, 86],
        [21, 92, 28, 96, 36, 98],
    ];

    private const START = [12.0, 12.0];

    private const NODE_SEGMENTS = 5;

    /** Segments (by index) that nodes are never placed on. */
    private const CONNECTOR_SEGMENTS = [2];

    /**
     * Equally spaced points along the node-carrying rows of the trail.
     *
     * @return list<array{x: float, y: float}>
     */
    public static function nodes(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $points = self::flatten();
        $lengths = self::cumulativeLengths($points);
        $total = end($lengths);

        return collect(range(0, $count - 1))
            ->map(fn (int $index): array => self::pointAtLength(
                $points,
                $lengths,
                $count === 1 ? 0.0 : $total * $index / ($count - 1),
            ))
            ->all();
    }

    /**
     * SVG path data for the whole trail, nodes and tail included. Fixed regardless of node count.
     */
    public static function trail(): string
    {
        [$x, $y] = self::START;
        $segments = ["M{$x},{$y}"];

        foreach (self::SEGMENTS as [$x1, $y1, $x2, $y2, $x, $y]) {
            $segments[] = "C{$x1},{$y1} {$x2},{$y2} {$x},{$y}";
        }

        return implode(' ', $segments);
    }

    /**
     * Polyline approximation of the node-carrying segments, each point tagged with its segment.
     *
     * @return list<array{0: float, 1: float, 2: int}>
     */
    private static function flatten(): array
    {
        $points = [[...self::START, 0]];
        [$px, $py] = self::START;

        foreach (array_slice(self::SEGMENTS, 0, self::NODE_SEGMENTS) as $segment => [$x1, $y1, $x2, $y2, $x, $y]) {
            for ($i = 1; $i <= self::SAMPLES_PER_SEGMENT; $i++) {
                $t = $i / self::SAMPLES_PER_SEGMENT;
                $mt = 1 - $t;
                $points[] = [
                    $mt ** 3 * $px + 3 * $mt ** 2 * $t * $x1 + 3 * $mt * $t ** 2 * $x2 + $t ** 3 * $x,
                    $mt ** 3 * $py + 3 * $mt ** 2 * $t * $y1 + 3 * $mt * $t ** 2 * $y2 + $t ** 3 * $y,
                    $segment,
                ];
            }

            [$px, $py] = [$x, $y];
        }

        return $points;
    }

    /**
     * Running arc length in rendered proportions; connector segments contribute nothing, so
     * equal spacing along these lengths skips them.
     *
     * @param  list<array{0: float, 1: float, 2: int}>  $points
     * @return list<float>
     */
    private static function cumulativeLengths(array $points): array
    {
        $lengths = [0.0];

        for ($i = 1; $i < count($points); $i++) {
            if (in_array($points[$i][2], self::CONNECTOR_SEGMENTS, true)) {
                $lengths[] = $lengths[$i - 1];

                continue;
            }

            $dx = ($points[$i][0] - $points[$i - 1][0]) * self::ASPECT;
            $dy = $points[$i][1] - $points[$i - 1][1];
            $lengths[] = $lengths[$i - 1] + sqrt($dx * $dx + $dy * $dy);
        }

        return $lengths;
    }

    /**
     * @param  list<array{0: float, 1: float, 2: int}>  $points
     * @param  list<float>  $lengths
     * @return array{x: float, y: float}
     */
    private static function pointAtLength(array $points, array $lengths, float $target): array
    {
        $last = count($points) - 1;

        for ($i = 1; $i <= $last; $i++) {
            if ($lengths[$i] >= $target) {
                $span = $lengths[$i] - $lengths[$i - 1];
                $f = $span > 0 ? ($target - $lengths[$i - 1]) / $span : 0.0;

                return [
                    'x' => round($points[$i - 1][0] + $f * ($points[$i][0] - $points[$i - 1][0]), 2),
                    'y' => round($points[$i - 1][1] + $f * ($points[$i][1] - $points[$i - 1][1]), 2),
                ];
            }
        }

        return ['x' => round($points[$last][0], 2), 'y' => round($points[$last][1], 2)];
    }
}
