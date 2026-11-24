<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_server_error_links_to_the_invalid_input_without_echoing_password()
    {
        $this->from('/login')->post('/login', ['email' => 'invalid', 'password' => 'fictional input'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->get('/login')->assertOk()
            ->assertSee('id="form-errors"', false)->assertSee('href="#email"', false)
            ->assertSee('aria-describedby="email-error"', false)->assertDontSee('fictional input');
    }

    public function test_staff_retry_recovers_only_the_submitted_account_and_reopens_its_form()
    {
        $this->signIn();
        $edited = $this->account('Existing training account', 'edited@example.test');
        $other = $this->account('Other training account', 'other@example.test');

        $this->from('/staff?page=1')->put('/staff/'.$edited->id, [
            'name' => 'Attempted training name', 'email' => $other->email,
            'role' => 'Market Staff', 'active' => '0',
            'password' => 'fictional password entry', 'password_confirmation' => 'fictional password entry',
            'staff_form' => (string) $other->id,
        ])->assertRedirect('/staff?page=1')->assertSessionHasErrors('email');

        $page = $this->get('/staff?page=1')->assertOk();
        $page->assertSee('href="#email-'.$edited->id.'"', false)->assertDontSee('href="#email-'.$other->id.'"', false);
        $page->assertSee('aria-describedby="email-'.$edited->id.'-error"', false);
        $page->assertSee('id="staff-account-'.$edited->id.'" open', false)->assertDontSee('id="staff-account-'.$other->id.'" open', false);
        $page->assertDontSee('fictional password entry');
        $this->assertSame(1, substr_count($page->getContent(), 'value="Attempted training name"'));
        $this->assertSame('Existing training account', $edited->fresh()->name);
    }

    public function test_staff_retry_does_not_default_rejected_role_or_status_to_privileged_choices(): void
    {
        $this->signIn();
        $edited = $this->account('Disabled training account', 'disabled@example.test');
        $edited->assignRole('Market Staff');
        $edited->update(['active' => false]);

        $this->from('/staff')->put('/staff/'.$edited->id, [
            'name' => $edited->name, 'email' => $edited->email, 'role' => 'Invalid role', 'active' => 'invalid',
        ])->assertRedirect('/staff')->assertSessionHasErrors(['role', 'active']);

        $doc = new \DOMDocument;
        @$doc->loadHTML($this->get('/staff')->assertOk()->getContent());
        $xpath = new \DOMXPath($doc);
        $id = $edited->id;
        $this->assertSame('Market Staff', $xpath->evaluate("string(//select[@id='role-$id']/option[@selected])"));
        $this->assertSame('0', $xpath->evaluate("string(//select[@id='active-$id']/option[@selected]/@value)"));
    }

    public function test_nested_registration_errors_link_to_the_retry_rows_without_echoing_id_number()
    {
        $this->signIn();
        $path = '/manage/tenants/create';
        $this->from($path)->post('/manage/tenants', [
            'profile_form' => '1', 'first_name' => 'Fictional', 'last_name' => 'Stallholder',
            'government_id_number' => 'FICTIONAL-ID-NUMBER', 'registration_action' => 'draft',
            'submission_key' => (string) Str::uuid(),
            'businesses' => ['new-2' => ['name' => 'Sample shop', 'status' => 'active']],
            'pending_assignments' => ['new-1' => ['business_key' => 'new-2', 'start_date' => 'bad-date']],
        ])->assertRedirect($path)->assertSessionHasErrors(['businesses.new-2.business_category_id', 'pending_assignments.new-1.start_date']);

        $this->get($path)->assertOk()->assertSee('href="#business-new-2-category"', false)
            ->assertSee('href="#stall-new-1-start"', false)
            ->assertSee('aria-describedby="stall-new-1-start-error"', false)->assertDontSee('FICTIONAL-ID-NUMBER');
    }

    public function test_section_error_keeps_existing_format_hint_and_links_to_color_control()
    {
        $this->signIn();
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['color' => 'Use a valid demonstration color.']))])
            ->get('/manage/sections/create')->assertOk()
            ->assertSee('href="#color"', false)
            ->assertSee('aria-describedby="section-color-format color-error"', false)
            ->assertSee('id="color-error"', false);
    }

    private function signIn(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->account('Training administrator', 'administrator@example.test');
        $admin->assignRole('Super Administrator');
        $this->actingAs($admin);
    }

    private function account(string $name, string $email): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => 'unused', 'active' => true]);
    }
}
