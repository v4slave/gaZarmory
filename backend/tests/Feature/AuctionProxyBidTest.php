<?php
namespace Tests\Feature;
use App\Enums\PlayerClass;use App\Enums\UserRole;use App\Models\Auction;use App\Models\AuctionBid;use App\Models\Player;use App\Models\TreasuryItem;use App\Models\TreasuryTransaction;use App\Models\User;use Illuminate\Foundation\Testing\DatabaseTransactions;use Illuminate\Support\Facades\DB;use Tests\TestCase;
final class AuctionProxyBidTest extends TestCase
{
 use DatabaseTransactions;
 public function test_reservation_proxy_bids_and_auto_extension():void
 {
  [$leader]=$this->user(UserRole::GuildLeader);[$first,$firstPlayer]=$this->user(UserRole::Member);[$second,$secondPlayer]=$this->user(UserRole::Member);
  DB::table('treasury_token_settings')->where('id',1)->update(['token_unit_value'=>80]);
  $item=TreasuryItem::query()->create(['item_name'=>'Proxy '.uniqid(),'quantity'=>5,'reserved_quantity'=>0,'unit_value'=>100]);
  $auction=Auction::query()->create(['treasury_item_id'=>$item->id,'quantity'=>2,'starting_bid'=>100,'minimum_step'=>10,'extension_minutes'=>3,'ends_at'=>now()->addMinutes(11),'status'=>'draft','created_by'=>$leader->id]);
  $this->actingAs($leader)->postJson('/api/auctions/'.$auction->id.'/start')->assertOk();
  self::assertSame(80,(int)$auction->fresh()->token_unit_value_snapshot);
  self::assertSame(2,(int)$item->fresh()->reserved_quantity);
  $auction->update(['ends_at'=>now()->addMinutes(2)]);$oldEnd=$auction->fresh()->ends_at;
  $this->actingAs($first)->postJson('/api/auctions/'.$auction->id.'/bid',['amount'=>500])->assertCreated()->assertJsonPath('amount',100);
  $this->actingAs($second)->postJson('/api/auctions/'.$auction->id.'/bid',['amount'=>300])->assertCreated()->assertJsonPath('player_id',$firstPlayer->id)->assertJsonPath('amount',310)->assertJsonPath('is_auto',true);
  self::assertTrue($auction->fresh()->ends_at->gt($oldEnd));
  $this->actingAs($second)->postJson('/api/auctions/'.$auction->id.'/bid',['amount'=>600])->assertCreated()->assertJsonPath('player_id',$secondPlayer->id)->assertJsonPath('amount',510);
  $this->assertDatabaseHas('auction_auto_bids',['auction_id'=>$auction->id,'player_id'=>$firstPlayer->id,'max_amount'=>500]);
  $this->assertDatabaseHas('auction_auto_bids',['auction_id'=>$auction->id,'player_id'=>$secondPlayer->id,'max_amount'=>600]);
 }
 public function test_bid_outside_extension_window_keeps_original_end():void
 {
  [$leader]=$this->user(UserRole::GuildLeader);[$member]=$this->user(UserRole::Member);
  DB::table('treasury_token_settings')->where('id',1)->update(['token_unit_value'=>80]);
  $item=TreasuryItem::query()->create(['item_name'=>'Long auction '.uniqid(),'quantity'=>1,'reserved_quantity'=>0,'unit_value'=>100]);
  $originalEnd=now()->addDay();
  $auction=Auction::query()->create(['treasury_item_id'=>$item->id,'quantity'=>1,'starting_bid'=>100,'minimum_step'=>10,'extension_minutes'=>3,'ends_at'=>$originalEnd,'status'=>'draft','created_by'=>$leader->id]);
  $this->actingAs($leader)->postJson('/api/auctions/'.$auction->id.'/start')->assertOk();
  $this->actingAs($member)->postJson('/api/auctions/'.$auction->id.'/bid',['amount'=>100])->assertCreated();
  self::assertSame($originalEnd->timestamp,$auction->fresh()->ends_at->timestamp);
  self::assertSame(0,(int)$auction->fresh()->extensions_count);
 }
 public function test_finishing_without_bids_explicitly_releases_reservation():void
 {
  [$leader]=$this->user(UserRole::GuildLeader);$item=TreasuryItem::query()->create(['item_name'=>'No bids '.uniqid(),'quantity'=>2,'reserved_quantity'=>1,'unit_value'=>100]);$auction=Auction::query()->create(['treasury_item_id'=>$item->id,'quantity'=>1,'starting_bid'=>100,'minimum_step'=>10,'extension_minutes'=>3,'ends_at'=>now()->subMinute(),'status'=>'active','created_by'=>$leader->id]);
  $this->actingAs($leader)->postJson('/api/auctions/'.$auction->id.'/finish')->assertOk()->assertJsonPath('status','finished')->assertJsonPath('winner_player_id',null);
  self::assertSame(0,(int)$item->fresh()->reserved_quantity);
 }
 public function test_winning_tokens_are_converted_to_gold_using_start_rate():void
 {
  [$leader]=$this->user(UserRole::GuildLeader);[, $winner]=$this->user(UserRole::Member);
  $item=TreasuryItem::query()->create(['item_name'=>'Token sale '.uniqid(),'quantity'=>1,'reserved_quantity'=>1,'unit_value'=>100]);
  $auction=Auction::query()->create(['treasury_item_id'=>$item->id,'quantity'=>1,'starting_bid'=>2,'minimum_step'=>1,'token_unit_value_snapshot'=>80,'ends_at'=>now()->subMinute(),'status'=>'active','created_by'=>$leader->id]);
  AuctionBid::query()->create(['auction_id'=>$auction->id,'player_id'=>$winner->id,'amount'=>5,'is_auto'=>false]);

  $this->actingAs($leader)->postJson('/api/auctions/'.$auction->id.'/finish')->assertOk()->assertJsonPath('winning_bid',5);
  $transaction=TreasuryTransaction::query()->where('related_entity_type',Auction::class)->where('related_entity_id',$auction->id)->firstOrFail();
  self::assertSame(400,(int)$transaction->amount);
 }
 public function test_archive_summarizes_wins_and_spending():void
 {
  [$member,$player]=$this->user(UserRole::Member);[$leader]=$this->user(UserRole::GuildLeader);$item=TreasuryItem::query()->create(['item_name'=>'Archive '.uniqid(),'quantity'=>1,'reserved_quantity'=>0,'unit_value'=>100]);Auction::query()->create(['treasury_item_id'=>$item->id,'quantity'=>1,'starting_bid'=>100,'minimum_step'=>10,'ends_at'=>now()->subDay(),'status'=>'finished','winner_player_id'=>$player->id,'winning_bid'=>450,'finished_at'=>now(),'created_by'=>$leader->id]);
  $response=$this->actingAs($member)->getJson('/api/auctions/archive')->assertOk();$row=collect($response->json('players'))->firstWhere('player_id',$player->id);self::assertSame(1,$row['wins']);self::assertSame(450,$row['spent']);
 }
 private function user(UserRole $role):array{$suffix=str_replace('.','',uniqid('',true));$user=User::query()->create(['discord_id'=>$suffix,'discord_username'=>'proxy'.$suffix]);$user->forceFill(['role'=>$role,'roles'=>[$role->value]])->save();$player=Player::query()->create(['nickname'=>'Proxy'.substr($suffix,-8),'class'=>PlayerClass::Melee,'is_active'=>true]);$player->forceFill(['user_id'=>$user->id])->save();$user->setRelation('player',$player);return[$user,$player];}
}
