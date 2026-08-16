<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\ActivityDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ActivityDefinitionIconTest extends TestCase
{
    use DatabaseTransactions;

    public function test_developer_can_upload_and_delete_activity_icon(): void
    {
        Storage::fake('public');
        $developer=$this->user(UserRole::Developer);
        $definition=ActivityDefinition::query()->create(['name'=>'Icon test '.uniqid(),'type'=>ActivityType::Prime,'is_active'=>true]);

        $response=$this->actingAs($developer)->postJson('/api/activity-definitions/'.$definition->id.'/icon',['icon'=>UploadedFile::fake()->image('boss.png',320,180)]);
        $response->assertOk();
        Storage::disk('public')->assertExists($definition->fresh()->icon_path);

        $this->actingAs($developer)->deleteJson('/api/activity-definitions/'.$definition->id.'/icon')->assertOk()->assertJsonPath('icon_url',null);
    }

    public function test_member_cannot_upload_activity_icon(): void
    {
        Storage::fake('public');
        $member=$this->user(UserRole::Member);
        $definition=ActivityDefinition::query()->firstOrFail();
        $this->actingAs($member)->postJson('/api/activity-definitions/'.$definition->id.'/icon',['icon'=>UploadedFile::fake()->image('boss.png')])->assertForbidden();
    }

    private function user(UserRole $role): User
    {
        $suffix=str_replace('.','',uniqid('',true));
        $user=User::query()->create(['discord_id'=>$suffix,'discord_username'=>'icon-'.$suffix]);
        $user->forceFill(['role'=>$role,'roles'=>[$role->value]])->save();
        return $user;
    }
}
