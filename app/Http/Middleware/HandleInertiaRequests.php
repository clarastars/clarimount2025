<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $userLanguage = $request->user()?->language ?? 'ar';
        
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'is_employee' => $request->user()?->roles()->where('name', 'employee')->wherePivotNull('team_id')->exists() ?? false,
            ],
            'locale' => $userLanguage,
            'translations' => $this->getTranslations($userLanguage),
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Get translations for the current language
     */
    private function getTranslations(string $locale): array
    {
        $translationPath = resource_path("lang/{$locale}/messages.php");
        
        if (file_exists($translationPath)) {
            return require $translationPath;
        }
        
        // Fallback to English if the language file doesn't exist
        return require resource_path('lang/en/messages.php');
    }
}
