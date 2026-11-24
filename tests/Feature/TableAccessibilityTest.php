<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scroll_regions_and_action_names_on_main_lists(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->create(['active' => true])->assignRole('Super Administrator'));
        Market::create(['name' => 'Fictional Market', 'code' => 'FM1', 'status' => 'active']);
        Tenant::create(['name' => 'Fictional Tenant', 'reference' => 'FT1', 'status' => 'active']);

        foreach ([
            '/manage/markets' => 'Markets',
            '/manage/tenants' => 'Tenant records',
            '/manage/stalls' => 'Stall records',
            '/assignments' => 'Stall assignments',
            '/billing' => 'Rental bills',
            '/payments' => 'Payment records',
            '/contracts' => 'Lease contracts',
            '/audit' => 'Audit logs',
        ] as $url => $label) {
            $headings = $this->page($url)->query('//*/table/parent::*[@role="region" and @tabindex="0" and @aria-label="'.$label.'"]/table/thead/tr/th[@scope="col"]');
            $this->assertGreaterThan(0, $headings->length, $url.' needs a named keyboard target around its semantic table.');
        }

        foreach ([
            '/manage/markets' => 'View Fictional Market (FM1)',
            '/manage/tenants' => 'View Fictional Tenant (FT1)',
            '/reports' => 'Open report for Stall Master List',
        ] as $url => $name) {
            $this->assertSame(1, $this->page($url)->query('//a[normalize-space(.)="'.$name.'"]')->length, $url.' needs a contextual link name.');
        }

        $this->assertSame(1, $this->page('/contracts')->query('//th[@scope="col" and normalize-space(.)="Actions"]')->length);
    }

    private function page(string $url): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadHTML($this->get($url)->assertOk()->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
