<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Settings\SettingsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', ['values' => $this->settings->form()]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $this->settings->update(SettingsData::fromRequest($request));

        return redirect()->route('admin.settings.edit')->with('success', 'Settings saved.');
    }
}
