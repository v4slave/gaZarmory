<?php

namespace App\Http\Controllers;

use App\Models\ActivityDefinition;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class ActivityDefinitionController extends Controller
{
    public function index() { return ActivityDefinition::query()->where('type', 'prime')->orderBy('name')->get(); }

    public function store(Request $request)
    {
        abort_unless($request->user()->canManageGuild(), 403);
        $data = $request->validate(['name' => ['required','string','max:160'], 'type' => ['required', Rule::in(['prime'])], 'is_active' => ['sometimes','boolean']]);
        return response()->json(ActivityDefinition::query()->create($data), 201);
    }

    public function update(Request $request, ActivityDefinition $activityDefinition)
    {
        abort_unless($request->user()->canManageGuild(), 403);
        $activityDefinition->update($request->validate(['name' => ['sometimes','required','string','max:160'], 'type' => ['sometimes', Rule::in(['prime'])], 'is_active' => ['sometimes','boolean']]));
        return $activityDefinition->refresh();
    }

    public function destroy(Request $request, ActivityDefinition $activityDefinition)
    {
        abort_unless($request->user()->canManageGuild(), 403);
        $activityDefinition->update(['is_active' => false]);
        return response()->noContent();
    }

    public function uploadIcon(Request $request, ActivityDefinition $activityDefinition, AuditService $audit)
    {
        abort_unless($request->user()->canManageGuild(), 403);
        $data=$request->validate(['icon' => ['required','image','mimes:png,jpg,jpeg,webp,gif','max:4096'],'updated_at'=>['nullable','date']]);
        if(isset($data['updated_at'])&&!$activityDefinition->updated_at->equalTo($data['updated_at']))throw \Illuminate\Validation\ValidationException::withMessages(['updated_at'=>'Активность уже изменена другим пользователем. Обновите страницу.']);
        $oldPath = $activityDefinition->icon_path;
        $newPath = $request->file('icon')->store('activity-definitions', 'public');
        $activityDefinition->update(['icon_path' => $newPath]);
        if ($oldPath) Storage::disk('public')->delete($oldPath);
        $audit->record('activity_definition.icon_updated', $activityDefinition, ['icon_path'=>$oldPath], ['icon_path'=>$newPath]);
        return $activityDefinition->refresh();
    }

    public function deleteIcon(Request $request, ActivityDefinition $activityDefinition, AuditService $audit)
    {
        abort_unless($request->user()->canManageGuild(), 403);
        $data=$request->validate(['updated_at'=>['nullable','date']]);
        if(isset($data['updated_at'])&&!$activityDefinition->updated_at->equalTo($data['updated_at']))throw \Illuminate\Validation\ValidationException::withMessages(['updated_at'=>'Активность уже изменена другим пользователем. Обновите страницу.']);
        $oldPath = $activityDefinition->icon_path;
        if ($oldPath) Storage::disk('public')->delete($oldPath);
        $activityDefinition->update(['icon_path' => null]);
        $audit->record('activity_definition.icon_deleted', $activityDefinition, ['icon_path'=>$oldPath], ['icon_path'=>null]);
        return $activityDefinition->refresh();
    }
}
