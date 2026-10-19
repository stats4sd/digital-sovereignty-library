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

it('keeps every formatting feature the RichEditor toolbar produces', function () {
    $html = '<p><strong>b</strong><em>i</em><u>u</u><s>s</s><sub>sub</sub><sup>sup</sup><a href="https://example.org" target="_blank">l</a></p>'
        .'<h2>H2</h2><h3>H3</h3><blockquote><p>quote</p></blockquote><pre><code>code</code></pre>'
        .'<ul><li><p>b</p></li></ul><ol start="3"><li><p>n</p></li></ol>'
        .'<table><tbody><tr><th rowspan="1" colspan="1"><p>H</p></th></tr><tr><td rowspan="1" colspan="1"><p>C</p></td></tr></tbody></table>';

    expect(HtmlSanitizer::clean($html))->toBe(str_replace(
        'target="_blank">',
        'target="_blank" rel="noopener noreferrer">',
        $html,
    ));
});

it('keeps text alignment on paragraphs and headings', function (string $align) {
    $html = "<p style=\"text-align: {$align};\">p</p><h2 style=\"text-align: {$align};\">h</h2>";

    expect(HtmlSanitizer::clean($html))->toBe($html);
})->with(['start', 'center', 'end', 'justify']);

it('drops any style other than a lone text-align', function (string $style) {
    expect(HtmlSanitizer::clean("<p style=\"{$style}\">p</p>"))->toBe('<p>p</p>');
})->with([
    'color: red',
    'text-align: center; background: url(https://evil.test/x)',
    'text-align: expression(alert(1))',
]);
