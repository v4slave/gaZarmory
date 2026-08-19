<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AdminWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_gets_paginated_users_and_configuration_status(): void
    {
        $leader=$this->user(UserRole::GuildLeader);

        $this->actingAs($leader)->getJson('/api/admin/users?search='.$leader->discord_username)
            ->assertOk()->assertJsonStructure(['data','current_page','last_page','total']);
        $this->actingAs($leader)->getJson('/api/admin/settings')
            ->assertOk()->assertJsonStructure(['economy'=>['token_unit_value'],'discord'=>['client_configured','webhook_configured'],'notifications'=>['total','unread'],'checked_at']);
    }

    public function test_member_cannot_read_administration_configuration(): void
    {
        $this->actingAs($this->user(UserRole::Member))->getJson('/api/admin/settings')->assertForbidden();
    }

    public function test_administrator_can_update_token_value_from_interface_api(): void
    {
        $leader=$this->user(UserRole::GuildLeader);
        $updatedAt=$this->actingAs($leader)->getJson('/api/admin/settings')->assertOk()->json('economy.token_updated_at');

        $this->actingAs($leader)->patchJson('/api/admin/settings/economy',[
            'token_unit_value'=>125,
            'updated_at'=>$updatedAt,
        ])->assertOk()->assertJsonPath('token_unit_value',125);

        $this->assertDatabaseHas('treasury_token_settings',['id'=>1,'token_unit_value'=>125]);
        self::assertNotNull(AuditLog::query()->where('action','treasury_token_setting.updated')->where('user_id',$leader->id)->first());
    }

    public function test_member_cannot_update_token_value(): void
    {
        $this->actingAs($this->user(UserRole::Member))->patchJson('/api/admin/settings/economy',[
            'token_unit_value'=>125,
        ])->assertForbidden();
    }

    public function test_stale_role_edit_is_rejected(): void
    {
        $leader=$this->user(UserRole::GuildLeader);
        $managed=$this->user(UserRole::Member);

        $this->actingAs($leader)->patchJson('/api/admin/users/'.$managed->id.'/roles',[
            'roles'=>[UserRole::Member->value,UserRole::PartyLeader->value],
            'updated_at'=>'2020-01-01T00:00:00Z',
        ])->assertUnprocessable()->assertJsonValidationErrors('updated_at');
    }

    private function user(UserRole $role):User
    {
        $suffix=str_replace('.','',uniqid('',true));
        $user=User::query()->create(['discord_id'=>$suffix,'discord_username'=>'admin-'.$suffix]);
        $user->forceFill(['role'=>$role,'roles'=>[$role->value]])->save();
        Player::query()->create(['nickname'=>'Admin'.substr($suffix,-8),'class'=>PlayerClass::Melee,'is_active'=>true])->forceFill(['user_id'=>$user->id])->save();
        return $user;
    }
}
