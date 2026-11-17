<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Nationality\NationalityGroupData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NationalityGroupRequest;
use App\Models\NationalityGroup;
use App\Services\NationalityGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Nationality groups (CR-10): CRUD, default group and country assignment.
 */
class NationalityGroupController extends Controller
{
    public function __construct(private readonly NationalityGroupService $groups) {}

    public function index(Request $request): View
    {
        $groups = $this->groups->all();
        $countries = $this->groups->countries();
        $selectedId = $request->query('group');
        $selected = $request->query('new') !== null
            ? new NationalityGroup(['is_active' => true, 'sort' => $groups->max('sort') + 1])
            : ($groups->firstWhere('id', (int) $selectedId) ?? $groups->first());

        return view('admin.nationality-groups.index', [
            'groups' => $groups,
            'selected' => $selected,
            'countries' => $countries,
            'unassigned' => $countries->whereNull('nationality_group_id')->values(),
            'defaultGroup' => $groups->firstWhere('is_default', true),
        ]);
    }

    public function store(NationalityGroupRequest $request): RedirectResponse
    {
        $group = $this->groups->create(NationalityGroupData::fromRequest($request));

        return redirect()->route('admin.nationality-groups.index', ['group' => $group->id])->with('success', "Group “{$group->name}” created.");
    }

    public function update(NationalityGroupRequest $request, NationalityGroup $nationalityGroup): RedirectResponse
    {
        $this->groups->update($nationalityGroup, NationalityGroupData::fromRequest($request));

        return redirect()->route('admin.nationality-groups.index', ['group' => $nationalityGroup->id])->with('success', "Group “{$nationalityGroup->name}” saved.");
    }

    public function destroy(NationalityGroup $nationalityGroup): RedirectResponse
    {
        $name = (string) $nationalityGroup->name;
        $this->groups->delete($nationalityGroup);

        return redirect()->route('admin.nationality-groups.index')->with('success', "Group “{$name}” deleted. Its countries now use the default group.");
    }
}
