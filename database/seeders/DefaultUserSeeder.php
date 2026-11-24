<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash, Validator};

class DefaultUserSeeder extends Seeder
{
    public function run()
    {
        if (config("default_admin.enabled") !== true) {
            return;
        }
        if (!app()->environment(["local", "testing"])) {
            $this->warn(
                "Default administrator creation is restricted to local/testing environments.",
            );
            return;
        }

        $name = config("default_admin.name");
        $email = config("default_admin.email");
        if (
            Validator::make(
                ["name" => $name, "email" => $email],
                [
                    "name" => "required|string|max:255",
                    "email" => "required|string|email|max:255",
                ],
            )->fails()
        ) {
            $this->warn(
                "Default administrator configuration is invalid; check the name and email.",
            );
            return;
        }
        $email = strtolower($email);
        $existing = User::whereRaw("LOWER(email) = ?", [$email])->first();
        if ($existing) {
            $this->reportExisting($existing);
            return;
        }

        $password = config("default_admin.password");
        // Bcrypt uses at most 72 bytes; reject longer input instead of silently truncating it.
        if (
            !is_string($password) ||
            strlen($password) < 16 ||
            strlen($password) > 72 ||
            strpos($password, chr(0)) !== false ||
            !preg_match("/[A-Z]/", $password) ||
            !preg_match("/[a-z]/", $password) ||
            !preg_match("/[0-9]/", $password) ||
            !preg_match("/[^A-Za-z0-9\s]/", $password)
        ) {
            $this->warn(
                "Default administrator configuration requires a password of 16-72 bytes with uppercase, lowercase, digit and symbol characters, without null bytes.",
            );
            return;
        }

        $user = DB::transaction(function () use ($name, $email, $password) {
            $this->call(RoleSeeder::class);
            $user = User::firstOrCreate(
                ["email" => $email],
                [
                    "name" => $name,
                    "password" => Hash::make($password),
                    "active" => true,
                ],
            );
            if ($user->wasRecentlyCreated) {
                $user->assignRole("Super Administrator");
            }
            return $user;
        });
        if (!$user->wasRecentlyCreated) {
            $this->reportExisting($user);
            return;
        }
        if ($this->command) {
            $this->command->info(
                "Default administrator created. Disable SEED_DEFAULT_ADMIN and remove DEFAULT_ADMIN_PASSWORD after provisioning.",
            );
        }
    }

    private function reportExisting(User $user)
    {
        if (!$user->hasRole("Super Administrator")) {
            $this->warn(
                "Configured email belongs to an existing non-administrator account; no account information, password or roles changed.",
            );
        } elseif ($this->command) {
            $this->command->info(
                "Administrator already exists; no account information, password or roles changed.",
            );
        }
    }

    private function warn($message)
    {
        if ($this->command) {
            $this->command->warn($message);
        }
    }
}
