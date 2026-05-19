<?php

namespace Tests\Feature;

use App\Events\AuctionUpdated;
use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Category;
use App\Models\User;
use App\Mail\AuctionWon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuctionOvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $buyer;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::create([
            'name' => 'Seller User',
            'email' => 'seller@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->buyer = User::create([
            'name' => 'Buyer User',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->category = Category::create([
            'name' => 'Antiques',
            'slug' => 'antiques',
        ]);
    }

    public function test_bid_outside_last_minute_does_not_trigger_overtime(): void
    {
        Event::fake();

        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Vintage Watch',
            'description' => 'A fine vintage watch.',
            'starting_price' => 100,
            'current_price' => 100,
            'end_time' => now()->addMinutes(10),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->buyer)
            ->postJson(route('bids.store', $auction), [
                'amount' => 120,
            ]);

        $response->assertStatus(200);

        $auction->refresh();
        $this->assertEquals(120, $auction->current_price);
        $this->assertFalse($auction->has_overtime_started);
        // End time should not be modified
        $this->assertTrue(now()->addMinutes(10)->diffInSeconds($auction->end_time) < 5);

        Event::assertDispatched(BidPlaced::class);
        Event::assertDispatched(AuctionUpdated::class);
    }

    public function test_bid_within_last_minute_triggers_overtime(): void
    {
        Event::fake();

        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Vintage Watch',
            'description' => 'A fine vintage watch.',
            'starting_price' => 100,
            'current_price' => 100,
            'end_time' => now()->addSeconds(30),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->buyer)
            ->postJson(route('bids.store', $auction), [
                'amount' => 120,
            ]);

        $response->assertStatus(200);

        $auction->refresh();
        $this->assertEquals(120, $auction->current_price);
        $this->assertTrue($auction->has_overtime_started);
        // End time should be extended to now + 60s
        $this->assertTrue(now()->addSeconds(60)->diffInSeconds($auction->end_time) < 5);

        Event::assertDispatched(BidPlaced::class);
        Event::assertDispatched(AuctionUpdated::class);
    }

    public function test_bid_during_overtime_resets_overtime_clock(): void
    {
        Event::fake();

        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Vintage Watch',
            'description' => 'A fine vintage watch.',
            'starting_price' => 100,
            'current_price' => 100,
            'end_time' => now()->addSeconds(30),
            'has_overtime_started' => true,
            'status' => 'active',
        ]);

        // Place another bid
        $response = $this->actingAs($this->buyer)
            ->postJson(route('bids.store', $auction), [
                'amount' => 130,
            ]);

        $response->assertStatus(200);

        $auction->refresh();
        $this->assertEquals(130, $auction->current_price);
        $this->assertTrue($auction->has_overtime_started);
        // End time should be reset to now + 60s
        $this->assertTrue(now()->addSeconds(60)->diffInSeconds($auction->end_time) < 5);
    }

    public function test_cron_command_transitions_to_overtime(): void
    {
        Event::fake();

        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Vintage Watch',
            'description' => 'A fine vintage watch.',
            'starting_price' => 100,
            'current_price' => 100,
            'end_time' => now()->subSeconds(5),
            'has_overtime_started' => false,
            'status' => 'active',
        ]);

        $this->artisan('auctions:close')
            ->assertExitCode(0);

        $auction->refresh();
        $this->assertTrue($auction->has_overtime_started);
        $this->assertEquals('active', $auction->status);
        $this->assertTrue(now()->addSeconds(60)->diffInSeconds($auction->end_time) < 5);

        Event::assertDispatched(AuctionUpdated::class);
    }

    public function test_cron_command_ends_overtime_auction_when_expired(): void
    {
        Event::fake();
        Mail::fake();

        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Vintage Watch',
            'description' => 'A fine vintage watch.',
            'starting_price' => 100,
            'current_price' => 100,
            'end_time' => now()->subSeconds(5),
            'has_overtime_started' => true,
            'status' => 'active',
        ]);

        // Place a bid so there is a winner
        DB::table('bids')->insert([
            'auction_id' => $auction->id,
            'buyer_id' => $this->buyer->id,
            'amount' => 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $auction->update(['current_price' => 150]);

        $this->artisan('auctions:close')
            ->assertExitCode(0);

        $auction->refresh();
        $this->assertEquals('ended', $auction->status);

        Mail::assertSent(AuctionWon::class, function ($mail) {
            return $mail->hasTo($this->buyer->email);
        });

        Event::assertDispatched(AuctionUpdated::class);
    }

    public function test_buy_it_now_ends_auction_even_with_bids(): void
    {
        Event::fake();
        Mail::fake();

        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Vintage Watch',
            'description' => 'A fine vintage watch.',
            'starting_price' => 100,
            'current_price' => 100,
            'buy_it_now_price' => 200,
            'end_time' => now()->addMinutes(10),
            'status' => 'active',
        ]);

        // Place a regular bid first
        $this->actingAs($this->buyer)
            ->postJson(route('bids.store', $auction), [
                'amount' => 120,
            ])
            ->assertStatus(200);

        // Buy it now
        $response = $this->actingAs($this->buyer)
            ->postJson(route('bids.buyItNow', $auction));

        $response->assertStatus(200);

        $auction->refresh();
        $this->assertEquals('ended', $auction->status);
        $this->assertEquals(200, $auction->current_price);

        Mail::assertSent(AuctionWon::class, function ($mail) {
            return $mail->hasTo($this->buyer->email);
        });
    }
}
