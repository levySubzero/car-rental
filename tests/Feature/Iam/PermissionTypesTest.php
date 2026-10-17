<?php

it('keeps the generated TypeScript permissions in sync with the enums', function () {
    $this->artisan('permissions:types --check')->assertSuccessful();
});
