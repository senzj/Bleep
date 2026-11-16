<?php

/**
 * The nav bars are styled in resources/css/app.css rather than with utility
 * classes, so these assertions guard the two rules that are easy to lose in a
 * future refactor.
 */
function navigationCss(): string
{
    return (string) file_get_contents(resource_path('css/app.css'));
}

it('keeps the logo free of the active button surface', function () {
    $css = navigationCss();

    expect($css)->toMatch(
        '/#app-navbar \.nav-brand,\s*\.compact-nav \.compact-nav__brand \{[^}]*background-color: transparent;[^}]*\}/',
    );

    expect($css)->toMatch(
        '/#app-navbar \.nav-brand:hover,\s*\.compact-nav \.compact-nav__brand:hover \{[^}]*background-color: color-mix\(/',
    );
});

it('gives compact icon links a pressable width', function () {
    $css = navigationCss();

    expect($css)->toMatch(
        '/\.compact-nav \.compact-nav__icon,\s*\.compact-nav \.nav-link \{[^}]*width: 2\.5rem;[^}]*min-width: 2\.5rem;[^}]*\}/',
    );
});
