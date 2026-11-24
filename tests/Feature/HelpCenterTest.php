<?php
namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function account($role = "Market Staff", $active = true)
    {
        $user = User::create([
            "name" => "Help fixture",
            "email" => uniqid("help-") . "@example.test",
            "password" => bcrypt("fixture-only"),
            "active" => $active,
        ]);
        $user->assignRole($role);
        return $user;
    }

    public function test_help_pages_and_downloads_require_an_active_authenticated_account()
    {
        foreach (
            [
                "/help",
                "/help/manual",
                "/help/quick-reference",
                "/help/workflows",
                "/help/faqs",
                "/help/download/manual-pdf",
                "/help/preview/manual-pdf",
            ]
            as $url
        ) {
            $this->get($url)->assertRedirect(route("login"));
        }
        $this->actingAs($this->account("Market Staff", false))
            ->get("/help")
            ->assertRedirect(route("login"));
    }

    public function test_all_six_existing_roles_can_read_help_and_have_the_four_menu_links()
    {
        foreach (array_keys(RoleSeeder::MATRIX) as $role) {
            $this->flushSession();
            $response = $this->actingAs($this->account($role))->get("/help");
            $response->assertSessionHasNoErrors();
            $response->assertOk()->assertSee("Help &amp; Support", false);
            foreach (["help.manual", "help.quick", "help.workflows", "help.faq"] as $route) {
                $response->assertSee(route($route), false);
                $this->get(route($route))->assertOk();
            }
        }
    }

    public function test_manual_has_fourteen_chapters_and_unknown_topics_are_rejected()
    {
        $response = $this->actingAs($this->account())->get("/help/manual")->assertOk();
        foreach (range(1, 14) as $chapter) {
            $response->assertSee("Chapter " . $chapter . " —");
        }
        $response->assertSee('id="chapter-1-introduction"', false);
        $this->get("/help/manual/unknown-topic")->assertNotFound();
        $this->get("/help/manual/..%2F.env")->assertNotFound();
    }

    public function test_help_does_not_grant_permission_to_an_unassigned_account()
    {
        $user = $this->account();
        $user->removeRole("Market Staff");
        $this->actingAs($user)->get("/help")->assertForbidden();
    }

    public function test_topic_search_and_category_filters_show_matching_chapters()
    {
        $this->actingAs($this->account())
            ->get("/help?q=Tenant+Management&category=management")
            ->assertOk()
            ->assertSee("Chapter 6 — Tenant Management")
            ->assertDontSee("Chapter 2 — Getting Started");
        $this->get("/help?q=not-a-real-help-topic-xyz")->assertOk()->assertSee("No topics found");
        $this->get("/help?q[]=bad")->assertSessionHasErrors("q");
        $this->get("/help?category=unknown")->assertSessionHasErrors("category");
    }

    public function test_markdown_cannot_render_raw_html_or_unsafe_links()
    {
        $file = tempnam(sys_get_temp_dir(), "markethub-manual-");
        file_put_contents(
            $file,
            "## Chapter 1 — Introduction\n<script>alert('fixture')</script>\n\n[Unsafe](javascript:alert%281%29)\n\n### Safe heading\nSafe content.\n",
        );
        config(["help.manual" => $file]);
        try {
            $this->actingAs($this->account())
                ->get("/help/manual/chapter-1-introduction")
                ->assertOk()
                ->assertSee("Safe content.")
                ->assertDontSee("<script>", false)
                ->assertDontSee('href="javascript:', false)
                ->assertSee('id="safe-heading"', false);
        } finally {
            unlink($file);
        }
    }

    public function test_workflow_images_are_authenticated_and_allowlisted()
    {
        $this->get("/help/workflow-images/01-overall")->assertRedirect(route("login"));
        $this->actingAs($this->account());
        $this->get("/help/workflow-images/not-allowlisted")->assertNotFound();
        $this->get("/help/workflow-images/..%2F.env")->assertNotFound();
        $file = tempnam(sys_get_temp_dir(), "markethub-diagram-");
        file_put_contents(
            $file,
            base64_decode(
                "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=",
            ),
        );
        config(["help.diagrams.01-overall.path" => $file]);
        try {
            $this->get("/help/workflow-images/01-overall")
                ->assertOk()
                ->assertHeader("Content-Type", "image/png");
        } finally {
            unlink($file);
        }
    }

    public function test_unified_training_screenshots_are_allowlisted_with_their_actual_image_type()
    {
        $this->actingAs($this->account())->get('/help/screenshots/categories--47-category-history')
            ->assertOk()->assertHeader('Content-Type','image/jpeg');
        $this->get('/help/screenshots/categories--unknown')->assertNotFound();
    }

    public function test_downloads_are_allowlisted_and_pdf_preview_is_inline()
    {
        $this->actingAs($this->account());
        $file = tempnam(sys_get_temp_dir(), "markethub-help-");
        file_put_contents($file, "%PDF-1.4\nHelp fixture");
        config(["help.downloads.manual-pdf.path" => $file]);
        try {
            $response = $this->get("/help/download/manual-pdf")->assertOk();
            $response->assertHeader("Content-Type", "application/pdf");
            $this->assertStringContainsString(
                "attachment;",
                $response->headers->get("Content-Disposition"),
            );
            $preview = $this->get("/help/preview/manual-pdf")->assertOk();
            $this->assertStringContainsString(
                "inline;",
                $preview->headers->get("Content-Disposition"),
            );
            $this->get("/help/download/.env")->assertNotFound();
            $this->get("/help/download/unknown")->assertNotFound();
            $this->get("/help/preview/manual-docx")->assertNotFound();
            config(["help.downloads.manual-pdf.path" => $file . "-missing"]);
            $this->get("/help/download/manual-pdf")->assertNotFound();
            $this->get("/help")->assertOk()->assertSee("Not available yet");
        } finally {
            unlink($file);
        }
    }
    public function test_manual_figures_use_controlled_authenticated_image_routes()
    {
        $this->get("/help/screenshots/authentication--01-login")->assertRedirect(route("login"));
        $this->actingAs($this->account())
            ->get("/help/screenshots/authentication--01-login")
            ->assertOk()
            ->assertHeader("Content-Type", "image/png");
        $this->get("/help/screenshots/unknown")->assertNotFound();
        $this->get("/help/screenshots/..%2F.env")->assertNotFound();
        $this->get("/help/manual/chapter-2-getting-started")
            ->assertOk()
            ->assertSee(route("help.screenshot", "authentication--01-login"), false)
            ->assertDontSee('src="screenshots/', false);
    }

    public function test_manual_keeps_the_first_figure_eager_and_wraps_semantic_tables()
    {
        $file = tempnam(sys_get_temp_dir(), 'markethub-manual-');
        file_put_contents($file, <<<'MARKDOWN'
## Chapter 1 — Introduction

### Account & access

![Opening diagram](workflows/01-overall.png)

| Stage | Result |
| --- | --- |
| Sign in | Dashboard |

## Chapter 2 — Getting Started

![Sign in screen](screenshots/authentication/01-login.png)

![Missing image](screenshots/authentication/missing.png)

`<table>`
MARKDOWN
        );
        config(['help.manual' => $file]);

        try {
            $this->actingAs($this->account())->get('/help/manual')->assertOk()
                ->assertSee('src="'.route('help.workflow-image', '01-overall').'" alt="Opening diagram" />', false)
                ->assertSee('src="'.route('help.screenshot', 'authentication--01-login').'" alt="Sign in screen" loading="lazy" />', false)
                ->assertSeeInOrder(['role="region"', 'aria-label="Account &amp; access table 1"', 'tabindex="0"', '<table>', '<th>Stage</th>', '</table>', '</div>'], false)
                ->assertDontSee('src="screenshots/', false)
                ->assertSee('<code>&lt;table&gt;</code>', false);

            $this->get('/help/manual/chapter-2-getting-started')->assertOk()
                ->assertSee('src="'.route('help.screenshot', 'authentication--01-login').'" alt="Sign in screen" />', false);
        } finally {
            unlink($file);
        }
    }
}
