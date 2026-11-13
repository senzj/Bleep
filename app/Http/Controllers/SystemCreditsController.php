<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class SystemCreditsController extends Controller
{
    /**
     * Show the unlisted credits page for the people and projects behind Bleep.
     */
    public function index(): View
    {
        return view('pages.credits', [
            'maintainer' => [
                'name' => 'senzj',
                'role' => 'Developer & maintainer',
                'github' => 'https://github.com/senzj',
            ],
            'projectUrl' => 'https://github.com/senzj/Bleep',
            'contributors' => [],
            'teams' => [],
            'builtWith' => [
                ['name' => 'Laravel', 'url' => 'https://laravel.com'],
                ['name' => 'Vue', 'url' => 'https://vuejs.org'],
                ['name' => 'Alpine.js', 'url' => 'https://alpinejs.dev'],
                ['name' => 'Tailwind CSS', 'url' => 'https://tailwindcss.com'],
                ['name' => 'DaisyUI', 'url' => 'https://daisyui.com'],
                ['name' => 'Laravel Reverb', 'url' => 'https://reverb.laravel.com'],
                ['name' => 'Chart.js', 'url' => 'https://www.chartjs.org'],
                ['name' => 'Lucide', 'url' => 'https://lucide.dev'],
            ],
            'inspiration' => [
                'name' => 'Laravel Chirper',
                'url' => 'https://bootcamp.laravel.com',
                'note' => 'Bleep started as a fork of Chirper, the social app built in the Laravel Bootcamp.',
            ],
        ]);
    }
}
