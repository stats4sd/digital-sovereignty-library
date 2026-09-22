<?php

use App\Support\Curriculum\MapLayout;

const ORIGINAL_TRAIL = 'M12,12 C23,7 35,7 46,12 C57,7 69,7 80,12 C94,14 98,42 88,53 C78,55 70,55 60,55 C48,55 38,55 26,55 C10,55 4,72 16,86 C21,92 28,96 36,98';

it('returns no nodes for an empty map', function () {
    expect(MapLayout::nodes(0))->toBe([]);
});

it('always draws the same hand-drawn trail', function () {
    expect(MapLayout::trail())->toBe(ORIGINAL_TRAIL);
});

it('puts a single node at the head of the trail', function () {
    expect(MapLayout::nodes(1))->toBe([['x' => 12.0, 'y' => 12.0]]);
});

it('keeps the seeded five modules close to their original hand-placed positions', function () {
    $nodes = MapLayout::nodes(5);
    $original = [[12, 12], [46, 12], [80, 12], [60, 55], [26, 55]];

    foreach ($original as $index => [$x, $y]) {
        expect(abs($nodes[$index]['x'] - $x))->toBeLessThanOrEqual(3.5)
            ->and(abs($nodes[$index]['y'] - $y))->toBeLessThanOrEqual(1.5);
    }
});

it('anchors the first and last nodes to the ends of the rows', function () {
    foreach ([2, 3, 6, 9] as $count) {
        $nodes = MapLayout::nodes($count);

        expect($nodes)->toHaveCount($count)
            ->and($nodes[0])->toBe(['x' => 12.0, 'y' => 12.0])
            ->and($nodes[$count - 1])->toBe(['x' => 26.0, 'y' => 55.0]);
    }
});

it('never places a node on the connecting bend', function () {
    foreach (range(2, 12) as $count) {
        foreach (MapLayout::nodes($count) as $node) {
            expect($node['x'])->toBeLessThanOrEqual(88.0)
                ->and($node['y'] <= 13.0 || $node['y'] >= 53.0)->toBeTrue();
        }
    }
});

it('spaces nodes evenly along the rows so they reshuffle when the count changes', function () {
    // Along the (straight-ish) rows, equal arc length means roughly equal horizontal gaps.
    $nodes = MapLayout::nodes(9);
    $top = array_column(array_slice($nodes, 0, 5), 'x');
    $bottom = array_column(array_slice($nodes, 5), 'x');

    $gaps = [...array_map(fn ($i) => $top[$i] - $top[$i - 1], range(1, 4)),
        ...array_map(fn ($i) => $bottom[$i - 1] - $bottom[$i], range(1, 3))];

    expect(max($gaps) - min($gaps))->toBeLessThan(1.0);
});
