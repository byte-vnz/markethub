<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Market, MarketLayout, MarketSection, Stall, StallLayoutPosition, Tenant, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class SectionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_master_counts_and_metadata(): void
    {
        $actor = $this->operator('Market Administrator');
        $market = $this->market('A');
        $description = 'Fictional produce <seasonal> & farm supplies';
        $this->post('/manage/sections', $this->sectionData($market, 'A', 'Fruit Hall') + [
            'description' => $description, 'color' => '#2366AC', 'sort_order' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $section = $market->sections()->firstOrFail();
        $this->assertDatabaseHas('market_sections', [
            'id' => $section->id, 'description' => $description, 'color' => '#2366AC',
            'sort_order' => 8, 'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
        $other = $this->section($market, 'B', 'Grains');
        $this->stall($section, 'O', 'occupied');
        $this->stall($section, 'V');
        $this->stall($section, 'M', 'maintenance');
        $url = '/manage/sections/'.$section->id;
        $this->get($url)->assertOk()->assertSee($description)->assertViewHas('record', function ($section) {
            return (int) $section->stalls_count === 3 && (int) $section->occupied_count === 1
                && (int) $section->vacant_count === 1 && (int) $section->maintenance_count === 1
                && (int) $section->reserved_count === 0 && (int) $section->inactive_count === 0;
        });
        $this->put($url, $this->sectionData($market, 'A', 'Fruit Hall', 'inactive'))->assertSessionHasErrors('status');
        $this->put('/manage/sections/'.$other->id, $this->sectionData($market, 'B', 'Grains', 'inactive'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('inactive', $other->refresh()->status);
        $this->post('/manage/sections/order', ['market_id' => $market->id, 'ids' => [$section->id, $other->id]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($section->refresh()->sort_order < $other->refresh()->sort_order);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sections.reordered', 'entity_id' => $section->id, 'actor_id' => $actor->id]);
        $this->get('/manage/sections?market_id='.$market->id)->assertOk()->assertSeeInOrder(['Fruit Hall', $description, 'Grains']);
        $this->get('/manage/sections?q=B&status=inactive&market_id='.$market->id)->assertOk()->assertSee('Grains')->assertDontSee('Fruit Hall');
    }

    public function test_section_duplicates_and_existing_ambiguous_spelling(): void
    {
        $this->operator();
        $a = $this->market('A');
        $b = $this->market('B');
        $legacy = $this->section($a, 'A', "  Dry\t Goods ");
        $sibling = $this->section($a, 'B', 'DRY GOODS');
        $this->post('/manage/sections', $this->sectionData($a, 'C', 'dry   goods'))->assertSessionHasErrors('name');
        $this->post('/manage/sections', $this->sectionData($a, 'A', 'Different'))->assertSessionHasErrors('code');
        $this->post('/manage/sections', $this->sectionData($b, 'A', 'dry goods'))->assertRedirect()->assertSessionHasNoErrors();
        $this->put('/manage/sections/'.$legacy->id, $this->sectionData($a, 'A', "Dry\t Goods") + ['description' => 'Updated notes'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame("  Dry\t Goods ", $legacy->refresh()->name);
        $this->put('/manage/sections/'.$sibling->id, $this->sectionData($a, 'B', 'Dry Goods'))->assertSessionHasErrors('name');
        $padded = $this->section($b, ' P ', 'Warehouse');
        $this->put('/manage/sections/'.$padded->id, $this->sectionData($b, 'P', 'Warehouse'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(' P ', $padded->refresh()->code);
        $this->post('/manage/sections', $this->sectionData($a, 'C', 'Different') + ['color' => 'red', 'sort_order' => -1])
            ->assertSessionHasErrors(['color', 'sort_order']);
        $this->post('/manage/sections/order', ['market_id' => $a->id, 'ids' => [$legacy->id, $b->sections()->value('id')]])
            ->assertSessionHasErrors('ids');
    }

    public function test_moving_an_unreferenced_section_checks_destination_and_preserves_its_original_spelling(): void
    {
        $this->operator();
        $first = $this->market('A');
        $conflicting = $this->market('B');
        $destination = $this->market('C');
        $section = $this->section($first, 'A', '  Dry Goods ');
        $this->section($conflicting, 'B', 'DRY GOODS');
        $path = '/manage/sections/'.$section->id;

        $this->put($path, $this->sectionData($conflicting, 'A', 'Dry Goods'))->assertSessionHasErrors('name');
        $this->assertSame($first->id, $section->refresh()->market_id);
        $this->put($path, $this->sectionData($destination, 'A', 'Dry Goods'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($destination->id, $section->refresh()->market_id);
        $this->assertSame('  Dry Goods ', $section->name);
    }

    public function test_bulk_section_reorder_is_limited_to_five_hundred_sections(): void
    {
        $this->operator();
        $market = $this->market('A');
        foreach (array_chunk(range(1, 500), 100) as $numbers) {
            DB::table('market_sections')->insert(array_map(function ($number) use ($market) {
                return $this->sectionData($market, (string) $number, 'Fictional section '.$number);
            }, $numbers));
        }
        $path = '/manage/sections?market_id='.$market->id;
        $this->get($path)->assertOk()->assertSee('Reorder sections for');
        $this->section($market, '501', 'Overflow section');
        $this->get($path)->assertOk()->assertDontSee('Reorder sections for')->assertSee('Display order');
        $this->post('/manage/sections/order', ['market_id' => $market->id, 'ids' => $market->sections()->pluck('id')->all()])
            ->assertSessionHasErrors('ids');
    }

    public function test_foreign_section_id_filter_is_ignored_and_retained_inactive_id_still_filters(): void
    {
        $this->operator();
        $market = $this->market('A');
        $other = $this->market('B');
        $active = $this->section($market, 'A', 'Local active section');
        $inactive = $this->section($market, 'B', 'Retained inactive section', 'inactive');
        $foreign = $this->section($other, 'A', 'Foreign section');
        $this->stall($active, 'LOCAL-STALL');
        $this->stall($inactive, 'RETAINED-STALL');
        $this->stall($foreign, 'FOREIGN-STALL');

        foreach (['/map', '/manage/stalls'] as $page) {
            $query = $page.'?market_id='.$market->id.'&section_id=';
            $this->get($query.$foreign->id)->assertOk()->assertSee('LOCAL-STALL')->assertSee('RETAINED-STALL')->assertDontSee('FOREIGN-STALL');
            $this->get($query.$inactive->id)->assertOk()->assertSee('RETAINED-STALL')->assertDontSee('LOCAL-STALL');
        }
    }

    public function test_section_and_stall_views_preserve_literal_zero_names_and_descriptions(): void
    {
        $this->operator();
        $market = $this->market('A');
        $section = $this->section($market, 'A', '0');
        $section->update(['description' => '0']);
        $stall = $this->stall($section, 'ZERO-SECTION-STALL');

        foreach (['/map', '/manage/stalls', '/map/stalls/'.$stall->id] as $page) {
            $this->get($page)->assertOk()->assertSee($stall->code)->assertDontSee('Unknown section');
        }
        $this->get('/manage/stalls/'.$stall->id)->assertOk()->assertSee('<dt>Section</dt><dd>0</dd>', false);
        $this->get('/manage/sections/'.$section->id)->assertOk()->assertSee('<dt>Description</dt><dd class="whitespace-pre-line">0</dd>', false);
    }

    public function test_stall_options_and_backend_reject_inactive_or_other_market_sections(): void
    {
        $this->operator();
        $a = $this->market('A');
        $b = $this->market('B');
        $active = $this->section($a, 'A', 'Fictional local active');
        $inactive = $this->section($a, 'B', 'Fictional local inactive', 'inactive');
        $foreign = $this->section($b, 'A', 'Fictional foreign active');
        $this->get('/manage/stalls/create?market_id='.$a->id)->assertOk()->assertSee($active->name)
            ->assertDontSee($foreign->name)->assertDontSee($inactive->name)->assertSee('Search sections');
        $this->getJson('/manage/stalls/section-options/'.$a->id)->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $active->id);
        $data = ['market_id' => $a->id, 'code' => 'NEW', 'classification' => 'Fictional', 'area' => '10.00', 'location_type' => 'inner', 'status' => 'vacant'];
        foreach ([$foreign, $inactive] as $unavailable) {
            $this->post('/manage/stalls', $data + ['market_section_id' => $unavailable->id])->assertSessionHasErrors('market_section_id');
        }
        $existing = $this->stall($inactive, 'EXISTING');
        $this->get('/manage/stalls/'.$existing->id.'/edit')->assertOk()->assertSee($inactive->name)->assertSee('Retained');
        $this->put('/manage/stalls/'.$existing->id, $data + ['market_section_id' => $inactive->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($inactive->id, $existing->refresh()->market_section_id);
        $this->get('/assignments/create')->assertOk()->assertDontSee('NEW');
        $mismatched = $this->stall($foreign, 'MISMATCH');
        $mismatched->update(['market_id' => $a->id]);
        $this->get('/assignments/create')->assertOk()->assertDontSee('MISMATCH');
        $tenant = Tenant::create(['reference' => 'FIX', 'name' => 'Fictional tenant']);
        $business = $tenant->businesses()->create(['name' => 'Fictional business']);
        foreach ([$existing, $mismatched] as $ineligible) {
            $this->post('/assignments', ['stall_id' => $ineligible->id, 'tenant_id' => $tenant->id, 'business_id' => $business->id,
                'start_date' => today()->toDateString(), 'agreement_reference' => 'FIC-0001'])->assertSessionHasErrors('assignment');
        }
        $this->assertDatabaseCount('stall_assignments', 0);
        $this->get('/manage/stalls?section_id='.$inactive->id)->assertOk()->assertSee('NEW');
    }

    public function test_section_rename_map_preserves_coordinates(): void
    {
        $this->operator();
        $market = $this->market('A');
        $section = $this->section($market, 'A', 'Old hall');
        $stall = $this->stall($section, 'MAP-1');
        $layout = MarketLayout::create(['market_id' => $market->id, 'name' => 'Fictional market layout']);
        $position = StallLayoutPosition::create(['market_layout_id' => $layout->id, 'stall_id' => $stall->id, 'column' => 3, 'row' => 2, 'width' => 2, 'height' => 1]);
        $before = $position->fresh()->getAttributes();
        $this->put('/manage/sections/'.$section->id, $this->sectionData($market, 'A', 'New hall') + ['color' => '#2366AC'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/map?market_id='.$market->id.'&section_id='.$section->id)->assertOk()
            ->assertSee('New hall')->assertSee('Section summary')->assertSee('#2366AC')->assertSee('grid-column:3 / span 2')->assertDontSee('Old hall');
        $this->get('/map/stalls/'.$stall->id)->assertOk()->assertSee('New hall');
        $this->assertSame($before, $position->fresh()->getAttributes());
        $this->assertSame($section->id, $stall->refresh()->market_section_id);
        $audit = AuditLog::where('action', 'sections.updated')->where('entity_id', $section->id)->firstOrFail();
        $this->assertSame('Old hall', $audit->old_values['name']);
        $this->assertSame('New hall', $audit->new_values['name']);
    }

    public function test_section_routes_enforce_permissions_and_preserve_referenced_records(): void
    {
        $actor = $this->operator();
        $a = $this->market('A');
        $b = $this->market('B');
        $section = $this->section($a, 'A', 'Fictional hall');
        $stall = $this->stall($section, 'PROTECTED');
        $elsewhere = $this->section($a, 'B', 'Fictional annex');
        $tenant = Tenant::create(['reference' => 'HIST', 'name' => 'Historical fictional tenant']);
        $stall->assignments()->create(['tenant_id' => $tenant->id, 'authorized_by' => $actor->id,
            'status' => 'ended', 'start_date' => '2024-01-01', 'end_date' => '2024-02-05']);
        $url = '/manage/sections/'.$section->id;
        $this->put($url, $this->sectionData($b, 'A', 'Fictional hall'))->assertSessionHasErrors('market_id');
        $this->put('/manage/stalls/'.$stall->id, ['market_id' => $a->id, 'market_section_id' => $elsewhere->id,
            'code' => $stall->code, 'classification' => $stall->classification, 'area' => $stall->area,
            'location_type' => $stall->location_type, 'status' => $stall->status])->assertSessionHasErrors('record');
        $this->delete($url)->assertStatus(405);
        $this->assertSame($section->id, $stall->refresh()->market_section_id);
        $viewer = $this->operator('LGU Management Viewer');
        $this->get('/manage/sections')->assertOk();
        $this->get($url)->assertOk();
        $this->get('/manage/sections/create')->assertForbidden();
        $this->get($url.'/edit')->assertForbidden();
        $this->post('/manage/sections/order', ['market_id' => $a->id, 'ids' => [$section->id]])->assertForbidden();
        $this->put($url, $this->sectionData($a, 'A', 'Renamed'))->assertForbidden();
        $this->getJson('/manage/stalls/section-options/'.$a->id)->assertForbidden();
        $legacy = Role::create(['name' => 'Fictional custom role']);
        $legacy->givePermissionTo(['markets.view', 'markets.manage']);
        $viewer->syncRoles([$legacy]);
        $this->actingAs($viewer->fresh())->get('/manage/sections')->assertForbidden();
        $this->seed(RoleSeeder::class);
        $this->assertFalse($legacy->fresh()->hasPermissionTo('sections.view'));
        $admin = Role::findByName('Market Administrator');
        $admin->revokePermissionTo(Permission::findByName('sections.manage'));
        $this->seed(RoleSeeder::class);
        $this->assertFalse($admin->fresh()->hasPermissionTo('sections.manage'));
    }

    public function test_populated_metadata_upgrade_and_read_only_reconciliation(): void
    {
        $this->withoutMockingConsoleOutput();
        $original = DB::getDefaultConnection();
        config(['database.connections.section_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('section_upgrade');
        try {
            foreach (['2014_10_12_000000_create_users_table.php' => 'CreateUsersTable', '2026_11_17_000001_create_market_schema.php' => 'CreateMarketSchema'] as $file => $class) {
                require_once database_path('migrations/'.$file);
                (new $class)->up();
            }
            DB::table('markets')->insert([['id' => 1, 'code' => 'A', 'name' => 'Fictional'], ['id' => 2, 'code' => 'B', 'name' => 'Fictional other']]);
            DB::table('market_sections')->insert([['id' => 9, 'market_id' => 1, 'name' => 'Dry  goods', 'code' => 'A'], ['id' => 10, 'market_id' => 1, 'name' => 'DRY GOODS', 'code' => 'B'], ['id' => 11, 'market_id' => 2, 'name' => '<info>Fictional section</info>', 'code' => 'C']]);
            DB::table('stalls')->insert([['id' => 5, 'market_id' => 1, 'market_section_id' => 9, 'code' => 'SYN', 'classification' => 'Synthetic'], ['id' => 6, 'market_id' => 2, 'market_section_id' => 9, 'code' => 'WRONG', 'classification' => 'Synthetic']]);
            DB::table('market_layouts')->insert(['id' => 7, 'market_id' => 1, 'name' => 'Fictional']);
            DB::table('stall_layout_positions')->insert(['stall_id' => 5, 'market_layout_id' => 7, 'row' => 4, 'column' => 3]);
            DB::table('users')->insert(['id' => 1, 'name' => 'Fictional treasurer', 'email' => 'fixture@example.test', 'password' => 'unused']);
            DB::table('tenants')->insert(['id' => 1, 'reference' => 'FTC', 'name' => 'Fictional tenant for preservation']);
            DB::table('stall_assignments')->insert(['id' => 1, 'stall_id' => 5, 'tenant_id' => 1, 'authorized_by' => 1, 'start_date' => '2024-01-01']);
            DB::table('rental_bills')->insert(['id' => 1, 'reference' => 'FIC-BILL', 'stall_assignment_id' => 1, 'tenant_id' => 1,
                'stall_id' => 5, 'period' => '2024-01', 'due_date' => '2024-01-31', 'amount' => '100.23', 'status' => 'posted', 'created_by' => 1]);
            DB::table('payments')->insert(['id' => 1, 'reference' => 'FIC-PAY', 'tenant_id' => 1, 'payment_date' => '2024-01-31',
                'amount' => '50.12', 'method' => 'cash', 'receipt_reference' => 'FIC-001', 'idempotency_key' => 'synthetic-payment-key',
                'request_hash' => 'synthetic-request-hash', 'status' => 'posted', 'recorded_by' => 1]);
            DB::table('payment_allocations')->insert(['payment_id' => 1, 'rental_bill_id' => 1, 'amount' => '50.12']);
            $before = (array) DB::table('market_sections')->where('id', 9)->first();
            $this->assertTrue(class_exists(\App\Console\Commands\ReconcileSections::class), 'The upgrade needs a read-only reconciliation command available before metadata migration.');
            $this->assertSame(0, Artisan::call('sections:reconcile'));
            $beforeReport = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([9, 10], $beforeReport['normalized_duplicate_groups'][0]['section_ids']);
            $this->assertSame(1, $beforeReport['counts']['market_mismatched_stall_sections']);
            $this->assertSame('<info>Fictional section</info>', $beforeReport['sections_by_market'][2][0]['name']);
            $this->assertSame('100.23', $beforeReport['preservation_controls']['financial']['posted_bill_amount']);
            $this->assertSame('50.12', $beforeReport['preservation_controls']['financial']['effective_payment_allocation_amount']);
            $this->assertSame('50.11', $beforeReport['preservation_controls']['financial']['posted_outstanding_balance']);
            require_once database_path('migrations/2026_11_19_000001_enhance_market_sections.php');
            (new \EnhanceMarketSections())->up();
            $this->assertSame(0, Artisan::call('sections:reconcile'));
            $afterReport = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($beforeReport['preservation_controls'], $afterReport['preservation_controls']);
            $after = (array) DB::table('market_sections')->where('id', 9)->first();
            $this->assertSame($before, array_intersect_key($after, $before));
            $this->assertSame(0, $after['sort_order']);
            $this->assertNull($after['created_by']);
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        } finally {
            DB::disconnect('section_upgrade');
            DB::setDefaultConnection($original);
        }
    }

    private function operator(string $role = 'Super Administrator'): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::create(['name' => 'Fictional operator', 'email' => User::count().'@example.test', 'password' => 'unused', 'active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);
        return $user;
    }

    private function market(string $code): Market
    {
        return Market::create(['code' => $code, 'name' => 'Fictional Market '.$code]);
    }

    private function sectionData(Market $market, string $code, string $name, string $status = 'active'): array
    {
        return ['market_id' => $market->id, 'code' => $code, 'name' => $name, 'status' => $status];
    }

    private function section(Market $market, string $code, string $name, string $status = 'active'): MarketSection
    {
        return MarketSection::create($this->sectionData($market, $code, $name, $status));
    }

    private function stall(MarketSection $section, string $code, string $status = 'vacant'): Stall
    {
        return Stall::create(['market_id' => $section->market_id, 'market_section_id' => $section->id, 'code' => $code,
            'classification' => 'Fictional', 'area' => '5.00', 'location_type' => 'inner', 'status' => $status]);
    }
}
