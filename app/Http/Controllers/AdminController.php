<?php
namespace App\Http\Controllers;
use App\Models\{User, AuditLog, SystemSetting};
use App\Services\Audit;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\{Rule, ValidationException};
class AdminController extends Controller
{
    public function staff()
    {
        $this->authorize("staff.manage");
        return view("admin.staff", [
            "users" => User::with("roles")->orderBy("name")->paginate(20),
            "roles" => array_keys(RoleSeeder::MATRIX),
        ]);
    }
    public function saveStaff(Request $r, $id = null)
    {
        $this->authorize("staff.manage");
        $r->merge(['staff_form' => $id === null ? 'new' : (string) $id]);
        $d = $r->validate([
            "name" => "required|string|max:120",
            "email" => [
                "required",
                "email",
                "max:255",
                Rule::unique("users", "email")->ignore($id),
            ],
            "password" => ($id ? "nullable" : "required") . "|string|min:12|max:128|confirmed",
            "role" => ["required", Rule::in(array_keys(RoleSeeder::MATRIX))],
            "active" => "required|boolean",
        ]);
        DB::transaction(function () use ($r, $d, $id) {
            $admins = User::role("Super Administrator")
                ->where("active", true)
                ->orderBy("id")
                ->lockForUpdate()
                ->get();
            $u = $id ? User::whereKey($id)->lockForUpdate()->firstOrFail() : new User();
            if (
                $id &&
                $u->hasRole("Super Administrator") &&
                (!$d["active"] || $d["role"] !== "Super Administrator") &&
                $admins->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    "role" => "Keep at least one active Super Administrator.",
                ]);
            }
            if ($id == $r->user()->id && !$d["active"]) {
                throw ValidationException::withMessages([
                    "active" => "You cannot disable your own account.",
                ]);
            }
            $u->fill(["name" => $d["name"], "email" => $d["email"], "active" => $d["active"]]);
            if (!empty($d["password"])) {
                $u->password = Hash::make($d["password"]);
                $u->setRememberToken(null);
            }
            $u->save();
            $u->syncRoles([$d["role"]]);
            Audit::record(
                $r->user(),
                $id ? "account.updated" : "account.created",
                $u,
                [],
                [
                    "name" => $u->name,
                    "email" => $u->email,
                    "role" => $d["role"],
                    "active" => $d["active"],
                ],
            );
        }, 3);
        return back()->with("success", "Account and role saved.");
    }
    public function audit(Request $r)
    {
        $this->authorize("audit.view");
        $q = AuditLog::visibleTo($r->user())->with("actor");
        if ($r->filled("q")) {
            $q->where("action", "like", "%" . substr($r->q, 0, 80) . "%");
        }
        return view("admin.audit", ["logs" => $q->latest()->paginate(30)->withQueryString()]);
    }
    public function settings()
    {
        $this->authorize("settings.manage");
        return view("admin.settings", ["settings" => SystemSetting::pluck("value", "key")]);
    }
    public function saveSettings(Request $r)
    {
        $this->authorize("settings.manage");
        $d = $r->validate([
            "organization_name" => "required|string|max:120",
            "notice" => "nullable|string|max:500",
        ]);
        DB::transaction(function () use ($r, $d) {
            foreach ($d as $key => $value) {
                SystemSetting::updateOrCreate(["key" => $key], ["value" => $value]);
            }
            Audit::record($r->user(), "settings.updated", null, [], $d);
        });
        return back()->with("success", "Settings saved.");
    }
}
