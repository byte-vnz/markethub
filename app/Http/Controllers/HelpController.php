<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HelpController extends Controller
{
    public function __construct()
    {
        $this->middleware("can:dashboard.view");
    }

    public function index(Request $request)
    {
        $request->validate([
            "q" => "nullable|string|max:160",
            "category" => ["nullable", "string", Rule::in(array_keys(config("help.categories")))],
        ]);
        $query = trim((string) $request->query("q", ""));
        $category = $request->query("category", "");
        $topics = array_filter($this->chapters(), function ($topic) use ($query, $category) {
            return (!$category || $topic["category"] === $category) &&
                ($query === "" || mb_stripos($topic["markdown"], $query) !== false);
        });
        return view("help.index", compact("topics", "query", "category") + $this->shared());
    }

    public function manual($topic = null)
    {
        $chapters = $this->chapters();
        abort_if($topic !== null && !isset($chapters[$topic]), 404);
        $sections = $topic === null ? $chapters : [$topic => $chapters[$topic]];
        return view(
            "help.reader",
            [
                "heading" => "User Manual",
                "sections" => $this->renderSections($sections, true),
                "chapters" => $chapters,
                "page" => "manual",
            ] + $this->shared(),
        );
    }

    public function quick()
    {
        return $this->document("Quick Reference", "quick");
    }

    public function workflows()
    {
        return $this->document("System Workflows", "workflows");
    }

    public function faq()
    {
        $sections = array_filter($this->chapters(), function ($chapter) {
            return $chapter["number"] === 13;
        });
        return view(
            "help.reader",
            [
                "heading" => "FAQs",
                "sections" => $this->renderSections($sections),
                "chapters" => [],
                "page" => "faq",
            ] + $this->shared(),
        );
    }

    public function download($document)
    {
        $file = $this->downloadFile($document);
        return response()->download($file["path"], $file["name"], [
            "Content-Type" => $file["mime"],
        ]);
    }

    public function preview($document)
    {
        $file = $this->downloadFile($document);
        abort_unless($file["mime"] === "application/pdf", 404);
        return response()->file($file["path"], [
            "Content-Type" => "application/pdf",
            "Content-Disposition" => 'inline; filename="' . $file["name"] . '"',
        ]);
    }

    public function diagram($diagram)
    {
        $file = config("help.diagrams")[$diagram] ?? null;
        abort_unless($file && is_file($file["path"]) && is_readable($file["path"]), 404);
        return response()->file($file["path"], ["Content-Type" => "image/png"]);
    }

    public function screenshot($image)
    {
        $path = config("help-screenshots")[$image] ?? null;
        abort_unless($path && is_file($path) && is_readable($path), 404);
        return response()->file($path, ["Content-Type" => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === "jpg" ? "image/jpeg" : "image/png"]);
    }

    private function downloadFile($document)
    {
        $file = config("help.downloads")[$document] ?? null;
        abort_unless($file && is_file($file["path"]) && is_readable($file["path"]), 404);
        return $file;
    }

    private function document($heading, $page)
    {
        $path = config("help." . $page);
        $markdown = is_file($path) && is_readable($path) ? file_get_contents($path) : "";
        return view(
            "help.reader",
            [
                "heading" => $heading,
                "sections" => $markdown !== "" ? [["html" => $this->markdown($markdown)]] : [],
                "chapters" => [],
                "page" => $page,
            ] + $this->shared(),
        );
    }

    private function chapters()
    {
        $path = config("help.manual");
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        preg_match_all(
            '/^## (Chapter (\d+) [^\r\n]+)\R(.*?)(?=^## Chapter \d+ |\z)/msu',
            file_get_contents($path),
            $matches,
            PREG_SET_ORDER,
        );
        $chapters = [];
        foreach ($matches as $match) {
            $number = (int) $match[2];
            foreach (config("help.categories") as $category => $group) {
                if (in_array($number, $group["chapters"], true)) {
                    $chapters[Str::slug($match[1])] = [
                        "title" => $match[1],
                        "number" => $number,
                        "category" => $category,
                        "markdown" => $match[0],
                    ];
                    break;
                }
            }
        }
        return $chapters;
    }

    private function shared()
    {
        return ["downloads" => config("help.downloads")];
    }

    private function renderSections(array $sections, bool $deferImages = false)
    {
        $keepFirstImageEager = $deferImages ? true : null;
        foreach ($sections as &$section) {
            $section["html"] = $this->markdown($section["markdown"], $keepFirstImageEager);
        }
        return $sections;
    }

    private function markdown($markdown, ?bool &$keepFirstImageEager = null)
    {
        $imageUrls = [];
        $markdown = preg_replace_callback(
            "/!\[([^\]]*)\]\((screenshots\/[^)]+|workflows\/[^)]+)\)/",
            function ($match) use (&$imageUrls) {
                $path = $match[2];
                if (strpos($path, "screenshots/") === 0) {
                    $parts = explode("/", $path);
                    $key = ($parts[1] ?? "") . "--" . pathinfo($parts[2] ?? "", PATHINFO_FILENAME);
                    $url = isset(config("help-screenshots")[$key])
                        ? route("help.screenshot", $key)
                        : null;
                } else {
                    $key = pathinfo($path, PATHINFO_FILENAME);
                    $url = isset(config("help.diagrams")[$key])
                        ? route("help.workflow-image", $key)
                        : null;
                }
                if (!$url) {
                    return $match[1];
                }
                $imageUrls[e($url)] = true;
                return "![" . $match[1] . "](" . $url . ")";
            },
            $markdown,
        );
        $html = Str::markdown($markdown, ["html_input" => "escape", "allow_unsafe_links" => false]);
        // Enhance only elements emitted by CommonMark after raw HTML is escaped.
        if ($keepFirstImageEager !== null) {
            $html = preg_replace_callback('/<img src="([^"]*)"[^>]* \/>/', function ($match) use ($imageUrls, &$keepFirstImageEager) {
                if (!isset($imageUrls[$match[1]])) {
                    return $match[0];
                }
                if ($keepFirstImageEager) {
                    $keepFirstImageEager = false;
                    return $match[0];
                }
                return substr($match[0], 0, -3) . ' loading="lazy" />';
            }, $html);
        }

        $heading = 'Help';
        $table = 0;
        return preg_replace_callback(
            '/<h([1-6])>(.*?)<\/h\1>|<\/?table>/s',
            function ($match) use (&$heading, &$table) {
                if ($match[0] === '<table>') {
                    $label = e($heading . ' table ' . ++$table);
                    return '<div class="help-table-scroll" role="region" aria-label="' . $label . '" tabindex="0"><table>';
                }
                if ($match[0] === '</table>') {
                    return '</table></div>';
                }
                $heading = html_entity_decode(strip_tags($match[2]), ENT_QUOTES, "UTF-8");
                $anchor = Str::slug($heading);
                return "<h" .
                    $match[1] .
                    ' id="' .
                    e($anchor) .
                    '">' .
                    $match[2] .
                    "</h" .
                    $match[1] .
                    ">";
            },
            $html,
        );
    }
}
