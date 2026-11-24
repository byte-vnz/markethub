<?php
namespace App\Http\Controllers;
use App\Http\Requests\LoginRequest;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    public function form()
    {
        return view("auth.login");
    }
    public function login(LoginRequest $request)
    {
        $key = "login:" . hash("sha256", strtolower($request->email) . "|" . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                "email" =>
                    "Too many attempts. Try again in " .
                    RateLimiter::availableIn($key) .
                    " seconds.",
            ]);
        }
        if (
            !Auth::attempt([
                "email" => $request->email,
                "password" => $request->password,
                "active" => true,
            ])
        ) {
            RateLimiter::hit($key, 60);
            Audit::record(null, "login.failed");
            throw ValidationException::withMessages([
                "email" => "The credentials are incorrect or this account is disabled.",
            ]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        Audit::record(Auth::user(), "login.success", Auth::user());
        return redirect()->intended(route("dashboard"));
    }
    public function logout(Request $request)
    {
        Audit::record($request->user(), "logout", $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route("login");
    }
}
