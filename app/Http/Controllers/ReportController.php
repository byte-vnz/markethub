<?php
namespace App\Http\Controllers;
use App\Services\Reports;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Writer\Xlsx, Cell\DataType};
use Dompdf\Dompdf;
class ReportController extends Controller
{
    public function index()
    {
        $this->authorize("reports.view");
        return view("reports.index", ["types" => Reports::TYPES]);
    }
    public function show(Request $r, $type)
    {
        abort_unless(isset(Reports::TYPES[$type]), 404);
        $spec = Reports::TYPES[$type];
        $this->authorize($spec[1]);
        $canSeeAssessmentMode = $r->user()->can('rentals.override.view');
        if ($type === 'rental-assessments' && $r->filled('computation_mode')) {
            $this->authorize('rentals.override.view');
        }
        if (in_array($type, ["monthly-billing", "receivables", "overdue", "payment-history"])) {
            $this->authorize("reports.personal");
        }
        $rules = [
            "market_id" => "nullable|integer|exists:markets,id",
            "section_id" => "nullable|integer|exists:market_sections,id",
            "stall_id" => "nullable|integer|exists:stalls,id",
            "tenant_id" =>
                ($type === "tenant-statement" ? "required" : "nullable") .
                "|integer|exists:tenants,id",
            "recorded_by" => "nullable|integer|exists:users,id",
            "business_category_id" => "nullable|integer|exists:business_categories,id",
            "location_type" => "nullable|in:inner,outer,unclassified",
            "from" => "nullable|date_format:Y-m-d",
            "to" => "nullable|date_format:Y-m-d|after_or_equal:from",
            "status" => "nullable|in:draft,posted,reversed,void",
            "computation_mode" => in_array($type, ['rental-assessments', 'rental-overrides'], true) ? "nullable|in:automatic,manual_override" : 'prohibited',
            "as_of" => in_array($type, ['rental-assessments', 'rental-overrides'], true) ? "nullable|date_format:Y-m-d" : 'prohibited',
            "format" => "nullable|in:csv,xlsx,pdf,print",
        ];
        if (in_array($type,['business-categories','rental-assessments','rental-overrides','lease-contract-registry'],true)) {
            foreach (array_diff(array_keys(Reports::scopeFilters('legacy')),array_keys(Reports::scopeFilters($type))) as $unsupported) {
                $rules[$unsupported] = 'prohibited';
            }
            if ($type === 'business-categories') {
                $rules['location_type'] = 'prohibited';
            }
            $rules['status'] = $type === 'lease-contract-registry' ? 'nullable|in:draft,ready,issued,signed,active,expired,terminated,void' : 'prohibited';
        }
        if ($type === 'rental-overrides') {
            foreach (['from', 'to', 'location_type'] as $unsupported) {
                $rules[$unsupported] = 'prohibited';
            }
            $rules['status'] = 'nullable|in:draft,pending,approved,rejected,revoked,expired';
        }
        $f = $r->validate($rules);
        if ($type === 'rental-overrides') {
            $f['as_of'] = $f['as_of'] ?? today()->toDateString();
        }
        $q = Reports::query($type, $f, $canSeeAssessmentMode);
        $title = $spec[0];
        $format = $f['format'] ?? null;
        $reportNote = $type === 'rental-overrides'
            ? 'Applicability on '.$f['as_of'].' uses current recorded decisions and the assignment term. Proposed values record the request. Issued assessments retain their saved values.'
            : null;
        if (!$format) {
            return response()->view("reports.show", [
                "rows" => $q->paginate(30)->appends($f),
                "title" => $title,
                "type" => $type,
                "filters" => $f,
                "reportNote" => $reportNote,
                "canSeeAssessmentMode" => $canSeeAssessmentMode,
            ])->header('Cache-Control', 'private, no-store');
        }
        abort_if(
            (clone $q)->getCountForPagination() > 5000,
            422,
            "Export exceeds 5,000 rows. Narrow the date or market filters.",
        );
        $rows = $q->get();
        $columns = $rows->isEmpty() ? ["No records"] : array_keys((array) $rows->first());
        if ($format === "print") {
            return response()->view("reports.print", compact("rows", "columns", "title", "reportNote"))->header('Cache-Control', 'private, no-store');
        }
        if ($format === "csv") {
            return response()->streamDownload(
                function () use ($rows, $columns) {
                    $out = fopen("php://output", "w");
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, $columns);
                    foreach ($rows as $row) {
                        fputcsv($out, array_map([Reports::class, "csvCell"], (array) $row));
                    }
                    fclose($out);
                },
                $type . ".csv",
                ["Content-Type" => "text/csv; charset=UTF-8", "Cache-Control" => "private, no-store"],
            );
        }
        if ($format === "xlsx") {
            return response()->streamDownload(
                function () use ($rows, $columns) {
                    $book = new Spreadsheet();
                    $sheet = $book->getActiveSheet();
                    $all = [array_values($columns)];
                    foreach ($rows as $row) {
                        $all[] = array_values((array) $row);
                    }
                    foreach ($all as $i => $values) {
                        foreach ($values as $j => $value) {
                            $sheet->setCellValueExplicitByColumnAndRow(
                                $j + 1,
                                $i + 1,
                                (string) $value,
                                DataType::TYPE_STRING,
                            );
                        }
                    }
                    $sheet->getStyle("1:1")->getFont()->setBold(true);
                    $sheet->freezePane("A2");
                    (new Xlsx($book))->save("php://output");
                    $book->disconnectWorksheets();
                },
                $type . ".xlsx",
                [
                    "Content-Type" =>
                        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                    "Cache-Control" => "private, no-store",
                ],
            );
        }
        $pdf = new Dompdf([
            "isRemoteEnabled" => false,
            "isPhpEnabled" => false,
            "chroot" => public_path(),
        ]);
        $pdf->loadHtml(view("reports.print", compact("rows", "columns", "title", "reportNote"))->render());
        $pdf->setPaper("A4", "landscape");
        $pdf->render();
        return response($pdf->output())
            ->header("Cache-Control", "private, no-store")
            ->header("Content-Type", "application/pdf")
            ->header("Content-Disposition", 'attachment; filename="' . $type . '.pdf"');
    }
}
