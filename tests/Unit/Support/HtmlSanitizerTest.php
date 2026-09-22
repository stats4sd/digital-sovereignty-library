<?php

use App\Support\HtmlSanitizer;

it('keeps tables and their cell spans', function () {
    $html = '<table><thead><tr><th colspan="2">Area</th></tr></thead><tbody><tr><td rowspan="2">A</td><td>B</td></tr></tbody></table>';

    $clean = HtmlSanitizer::clean($html);

    expect($clean)->toContain('<table>')
        ->and($clean)->toContain('<thead>')
        ->and($clean)->toContain('<tbody>')
        ->and($clean)->toContain('<th colspan="2">')
        ->and($clean)->toContain('<td rowspan="2">');
});

it('strips scripts, event handlers and styling attributes from tables', function () {
    $html = '<table style="color:red" onclick="x()"><tr><td class="a" onmouseover="y()">Cell<script>alert(1)</script></td></tr></table>';

    $clean = HtmlSanitizer::clean($html);

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onmouseover')
        ->and($clean)->not->toContain('style=')
        ->and($clean)->not->toContain('class=')
        ->and($clean)->toContain('<td>Cell');
});

it('passes null and empty strings through untouched', function () {
    expect(HtmlSanitizer::clean(null))->toBeNull()
        ->and(HtmlSanitizer::clean(''))->toBe('');
});
