<?php

use Illuminate\Support\Facades\Blade;

test('invitation link display escapes the credential and offers a selectable fallback', function () {
    $html = Blade::render('<x-staff.invitation-link :value="$value" />', [
        'value' => 'https://example.test/invite/abc\" autofocus onfocus=\"alert(1)',
    ]);

    expect($html)->toContain('readonly', 'data-invitation-link', '&quot;')
        ->not->toContain('value="https://example.test/invite/abc" autofocus');
});
