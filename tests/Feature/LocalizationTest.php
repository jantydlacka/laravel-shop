<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

it('translates every framework message into Czech', function (string $file) {
    $english = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php");
    $czech = require lang_path("cs/{$file}.php");

    expect(array_keys(Arr::dot($czech)))->toEqualCanonicalizing(array_keys(Arr::dot($english)));
})->with(['auth', 'pagination', 'passwords', 'validation']);

it('shows validation errors in Czech', function () {
    $errors = Validator::make(['title' => ''], ['title' => 'required'], [], ['title' => 'název'])->errors();

    expect(app()->getLocale())->toBe('cs')
        ->and($errors->first('title'))->toBe('Pole název je povinné.');
});
