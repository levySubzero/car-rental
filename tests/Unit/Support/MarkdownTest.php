<?php

use App\Support\Markdown;

it('renders Markdown to HTML', function () {
    expect(Markdown::toHtml("### Fuel\n\n- Full to **full**"))
        ->toBe("<h3>Fuel</h3>\n<ul>\n<li>Full to <strong>full</strong></li>\n</ul>");
});

it('strips raw HTML and unsafe links', function () {
    $html = Markdown::toHtml("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1)) [ok](https://baines.test)");

    expect($html)->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->toContain('<a href="https://baines.test">ok</a>');
});

it('renders nothing for empty text', function () {
    expect(Markdown::toHtml(null))->toBe('')
        ->and(Markdown::toHtml('  '))->toBe('');
});

it('spots TODO and [CONFIRM] markers', function () {
    expect(Markdown::hasTodo('TODO: paybill'))->toBeTrue()
        ->and(Markdown::hasTodo('Pay to the TODOS account'))->toBeFalse()
        ->and(Markdown::hasConfirm('Minimum age 23 [CONFIRM: 24?]'))->toBeTrue()
        ->and(Markdown::hasConfirm('Minimum age 23'))->toBeFalse();
});
