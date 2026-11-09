<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    /**
     * All settings as a typed key => value map (cached).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::all()
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->typedValue()])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->get($key, $default));
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) ($this->get($key, $default));
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Persist a batch of settings and refresh the cache.
     *
     * @param  array<string, mixed>  $values  key => raw value
     * @param  array<string, string>  $types  key => type (string|int|bool|json)
     */
    public function setMany(array $values, array $types = []): void
    {
        foreach ($values as $key => $value) {
            $existing = Setting::where('key', $key)->first();
            $type = $types[$key] ?? $existing?->type ?? 'string';

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => Setting::encode($value, $type), 'type' => $type],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Public-facing subset of settings surfaced to the website.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $all = $this->all();

        return [
            'site_title' => $all['site_title'] ?? 'LuxeShine Cleaning',
            'tagline' => $all['tagline'] ?? null,
            'hero_headline' => $all['hero_headline'] ?? null,
            'hero_subline' => $all['hero_subline'] ?? null,
            'support_phone' => $all['support_phone'] ?? null,
            'support_email' => $all['support_email'] ?? null,
            'office_address' => $all['office_address'] ?? null,
            'currency' => $all['currency'] ?? 'NZD',
            'business_open_time' => $all['business_open_time'] ?? null,
            'business_close_time' => $all['business_close_time'] ?? null,
            'service_areas' => $all['service_areas'] ?? [],
            'accepting_bookings' => (bool) ($all['accepting_bookings'] ?? true),
            'maintenance_mode' => (bool) ($all['maintenance_mode'] ?? false),
            'maintenance_message' => $all['maintenance_message'] ?? null,
        ];
    }
}
