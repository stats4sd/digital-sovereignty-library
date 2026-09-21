<?php

use App\Support\Curriculum\MapLayout;

it('returns no nodes for an empty map', function () {
    expect(MapLayout::nodes(0))->toBe([])
        ->and(MapLayout::trail([]))->toBe('');
});

it('centres a single node between the two rows', function () {
    expect(MapLayout::nodes(1))->toBe([['x' => 50.0, 'y' => 35.0]]);
});

it('spreads nodes evenly across the width and alternates rows', function () {
    $nodes = MapLayout::nodes(5);

    expect(array_column($nodes, 'x'))->toBe([12.0, 31.0, 50.0, 69.0, 88.0])
        ->and(array_column($nodes, 'y'))->toBe([14.0, 56.0, 14.0, 56.0, 14.0]);
});

it('cycles three rows above the two-row limit and keeps nodes inside the horizontal margins', function () {
    $nodes = MapLayout::nodes(9);

    expect(count($nodes))->toBe(9)
        ->and(min(array_column($nodes, 'x')))->toBe(MapLayout::LEFT)
        ->and(max(array_column($nodes, 'x')))->toBe(MapLayout::RIGHT)
        ->and(array_column($nodes, 'y'))->toBe([14.0, 35.0, 56.0, 14.0, 35.0, 56.0, 14.0, 35.0, 56.0]);

    expect(array_unique(array_column(MapLayout::nodes(7), 'y')))->toHaveCount(2);
});

it('draws an even-count trail whose tail leaves the bottom row', function () {
    $trail = MapLayout::trail(MapLayout::nodes(2));

    expect($trail)->toBe('M12,14 C50,14 50,56 88,56 C98,78 62,96 50,100');
});

it('draws the trail from the first node through each node to the toolkit tail', function () {
    $nodes = MapLayout::nodes(3);
    $trail = MapLayout::trail($nodes);

    expect($trail)->toStartWith('M12,14 ')
        ->toContain(' 50,56 ')
        ->toContain(' 88,14 ')
        ->toEndWith(' 62,96 50,100')
        ->and(substr_count($trail, 'C'))->toBe(3);
});
