<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok($this->settings->all());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $values = $request->validated();

        if ($values === []) {
            return $this->ok($this->settings->all(), 'Nothing to update.');
        }

        $this->settings->setMany($values, $request->settingTypes());
        $this->activity->log('settings.updated', null, ['keys' => array_keys($values)]);

        return $this->ok($this->settings->all(), 'Settings updated.');
    }
}
