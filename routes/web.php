<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    DashboardController,
    RecordController,
    MapController,
    AssignmentController,
    BillingController,
    PaymentController,
    ReportController,
    AdminController,
};
require __DIR__.'/business-categories.php';
require __DIR__.'/tenant-businesses.php';
require __DIR__.'/rental-overrides.php';

Route::get("/", function () {
    return redirect()->route("dashboard");
});
Route::middleware("guest")->group(function () {
    Route::get("/login", [AuthController::class, "form"])->name("login");
    Route::post("/login", [AuthController::class, "login"])->middleware("throttle:20,1");
});
Route::middleware(["auth", \App\Http\Middleware\ActiveAccount::class])->group(function () {
    Route::post("/logout", [AuthController::class, "logout"])->name("logout");
    Route::get("/dashboard", DashboardController::class)->name("dashboard");
    Route::get("/manage/{type}", [RecordController::class, "index"])->name("records.index");
    Route::get("/manage/{type}/create", [RecordController::class, "create"])->name(
        "records.create",
    );
    Route::post("/manage/{type}", [RecordController::class, "store"])->name("records.store");
    Route::post('/manage/sections/order', [RecordController::class, 'reorderSections'])->name('sections.order');
    Route::get('/manage/stalls/section-options/{market}', [RecordController::class, 'stallSectionOptions'])->name('stalls.section-options');
    Route::get("/manage/{type}/{id}/edit", [RecordController::class, "edit"])->name("records.edit");
    Route::put("/manage/{type}/{id}", [RecordController::class, "update"])->name("records.update");
    Route::get("/manage/{type}/{id}", [RecordController::class, "show"])->name("records.show");
    Route::get("/map", [MapController::class, "index"])->name("map");
    Route::get("/map/stalls/{stall}", [MapController::class, "details"])->name("map.details");
    Route::get("/map/layout/{market}", [MapController::class, "editor"])->name("map.editor");
    Route::post("/map/layout/{market}", [MapController::class, "save"])->name("map.save");
    Route::get("/assignments", [AssignmentController::class, "index"])->name("assignments.index");
    Route::get("/assignments/create", [AssignmentController::class, "create"])->name(
        "assignments.create",
    );
    Route::post("/assignments", [AssignmentController::class, "store"])->name("assignments.store");
    Route::post("/assignments/{assignment}/end", [AssignmentController::class, "end"])->name(
        "assignments.end",
    );
    Route::get("/assignments/{assignment}/transfer", [
        AssignmentController::class,
        "transferForm",
    ])->name("assignments.transfer-form");
    Route::post("/assignments/{assignment}/transfer", [
        AssignmentController::class,
        "transfer",
    ])->name("assignments.transfer");
    Route::get("/billing", [BillingController::class, "index"])->name("billing.index");
    Route::get("/billing/create", [BillingController::class, "create"])->name("billing.create");
    Route::post("/billing/generate", [BillingController::class, "generate"])->name(
        "billing.generate",
    );
    Route::get("/billing/{bill}", [BillingController::class, "show"])->name("billing.show");
    Route::post("/billing/{bill}/post", [BillingController::class, "post"])->name("billing.post");
    Route::post("/billing/{bill}/adjust", [BillingController::class, "adjust"])->name(
        "billing.adjust",
    );
    Route::get("/receivables", [BillingController::class, "receivables"])->name("receivables");
    Route::get("/payments", [PaymentController::class, "index"])->name("payments.index");
    Route::get("/payments/create", [PaymentController::class, "create"])->name("payments.create");
    Route::post("/payments", [PaymentController::class, "store"])->name("payments.store");
    Route::get("/payments/{payment}", [PaymentController::class, "show"])->name("payments.show");
    Route::post("/payments/{payment}/post", [PaymentController::class, "post"])->name(
        "payments.post",
    );
    Route::post("/payments/{payment}/reverse", [PaymentController::class, "reverse"])->name(
        "payments.reverse",
    );
    Route::post("/payments/{payment}/reconcile", [PaymentController::class, "reconcile"])->name(
        "payments.reconcile",
    );
    Route::get("/reports", [ReportController::class, "index"])->name("reports.index");
    Route::get("/reports/{type}", [ReportController::class, "show"])->name("reports.show");
    Route::get("/staff", [AdminController::class, "staff"])->name("staff");
    Route::post("/staff", [AdminController::class, "saveStaff"])->name("staff.store");
    Route::put("/staff/{id}", [AdminController::class, "saveStaff"])->name("staff.update");
    Route::get("/audit", [AdminController::class, "audit"])->name("audit");
    Route::get("/settings", [AdminController::class, "settings"])->name("settings");
    Route::post("/settings", [AdminController::class, "saveSettings"])->name("settings.save");
});

Route::post("/payments/{payment}/cancel", [PaymentController::class, "cancel"])
    ->middleware(["auth", \App\Http\Middleware\ActiveAccount::class])
    ->name("payments.cancel");

Route::middleware(["auth", \App\Http\Middleware\ActiveAccount::class])->group(function () {
    Route::get("/help", [\App\Http\Controllers\HelpController::class, "index"])->name("help.index");
    Route::get("/help/manual/{topic?}", [\App\Http\Controllers\HelpController::class, "manual"])
        ->where("topic", "[a-z0-9-]+")
        ->name("help.manual");
    Route::get("/help/quick-reference", [
        \App\Http\Controllers\HelpController::class,
        "quick",
    ])->name("help.quick");
    Route::get("/help/workflows", [\App\Http\Controllers\HelpController::class, "workflows"])->name(
        "help.workflows",
    );
    Route::get("/help/faqs", [\App\Http\Controllers\HelpController::class, "faq"])->name(
        "help.faq",
    );
    Route::get("/help/download/{document}", [
        \App\Http\Controllers\HelpController::class,
        "download",
    ])
        ->where("document", "[a-z0-9-]+")
        ->name("help.download");
    Route::get("/help/preview/{document}", [\App\Http\Controllers\HelpController::class, "preview"])
        ->where("document", "[a-z0-9-]+")
        ->name("help.preview");
    Route::get("/help/workflow-images/{diagram}", [
        \App\Http\Controllers\HelpController::class,
        "diagram",
    ])
        ->where("diagram", "[a-z0-9-]+")
        ->name("help.workflow-image");
});

Route::middleware(["auth", \App\Http\Middleware\ActiveAccount::class])->group(function () {
    Route::get("/contracts", [\App\Http\Controllers\LeaseContractController::class, "index"])->name(
        "contracts.index",
    );
    Route::get("/contracts/create", [
        \App\Http\Controllers\LeaseContractController::class,
        "create",
    ])->name("contracts.create");
    Route::post("/contracts", [
        \App\Http\Controllers\LeaseContractController::class,
        "store",
    ])->name("contracts.store");
    Route::get("/contract-templates", [
        \App\Http\Controllers\LeaseContractController::class,
        "templates",
    ])->name("contract-templates.index");
    Route::post("/contract-templates", [
        \App\Http\Controllers\LeaseContractController::class,
        "registerTemplate",
    ])->name("contract-templates.store");
    Route::post("/contract-templates/{template}/approve", [
        \App\Http\Controllers\LeaseContractController::class,
        "approveTemplate",
    ])->name("contract-templates.approve");
    Route::get("/contract-templates/{template}/download", [
        \App\Http\Controllers\LeaseContractController::class,
        "downloadTemplate",
    ])->name("contract-templates.download");
    Route::get("/contracts/{contract}", [
        \App\Http\Controllers\LeaseContractController::class,
        "show",
    ])->name("contracts.show");
    Route::post("/contracts/{contract}/generate", [
        \App\Http\Controllers\LeaseContractController::class,
        "generate",
    ])->name("contracts.generate");
    Route::post("/contracts/{contract}/signed", [
        \App\Http\Controllers\LeaseContractController::class,
        "upload",
    ])->name("contracts.upload");
    Route::post("/contracts/{contract}/action/{action}", [
        \App\Http\Controllers\LeaseContractController::class,
        "transition",
    ])->name("contracts.action");
    Route::get("/contracts/{contract}/download/{format}", [
        \App\Http\Controllers\LeaseContractController::class,
        "download",
    ])->name("contracts.download");
});

Route::get("/help/screenshots/{image}", [\App\Http\Controllers\HelpController::class, "screenshot"])
    ->middleware(["auth", \App\Http\Middleware\ActiveAccount::class])
    ->where("image", "[a-zA-Z0-9-]+")
    ->name("help.screenshot");
