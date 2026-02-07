<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplyLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Check if the app_settings table exists before querying
            if (Schema::hasTable('app_settings')) {
                $locale = AppSetting::get('locale', config('app.locale', 'en'));
                app()->setLocale($locale);
            }
        } catch (\Exception $e) {
            // If database is not available, use the default locale
            app()->setLocale(config('app.locale', 'en'));
        }

        return $next($request);
    }
}
