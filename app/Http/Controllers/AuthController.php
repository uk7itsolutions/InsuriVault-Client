<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthenticationServiceException;
use App\Exceptions\HostNotActiveException;
use App\Services\InsuriVaultApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    private const CREDENTIALS_REJECTED_MESSAGE = 'The provided credentials do not match our records.';

    private const HOST_NOT_ACTIVE_MESSAGE = 'Service not active for the current host.';

    private const SERVICE_UNAVAILABLE_MESSAGE = 'Sign-in is temporarily unavailable. Please try again shortly, if this persists please contact %s.';

    private const UNNAMED_ORGANIZATION = 'your administrator';

    protected $apiService;

    public function __construct(InsuriVaultApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function showLogin()
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController showLogin called');
        }
        if (Session::has('api_token')) {
            return redirect()->route('documents.index');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController login called', ['email' => $request->email]);
        }
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $token = $this->apiService->getToken($credentials['email'], $credentials['password']);
        } catch (HostNotActiveException $exception) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::debug('AuthController login failed, no host active for this portal');
            }

            if ($request->wantsJson()) {
                return response()->json(['error' => self::HOST_NOT_ACTIVE_MESSAGE], 403);
            }

            return back()->withErrors([
                'email' => self::HOST_NOT_ACTIVE_MESSAGE,
            ]);
        } catch (AuthenticationServiceException $exception) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::debug('AuthController login failed, the service could not answer');
            }

            if ($request->wantsJson()) {
                return response()->json(['error' => $this->serviceUnavailableMessage()], 503);
            }

            return back()->withErrors([
                'email' => $this->serviceUnavailableMessage(),
            ]);
        }

        if ($token) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::debug('AuthController login success, setting session and redirecting', [
                    'session_id' => Session::getId(),
                ]);
            }
            Session::put('api_token', $token);
            Session::put('user_email', $credentials['email']);
            Session::save(); // Explicitly save the session before redirecting

            // The login page posts here as JSON when the biometric button is pressed with a
            // password filled, so it can run the registration ceremony next instead of following a
            // redirect. Reusing this route keeps the surface that accepts a password to one.
            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('documents.index');
        }

        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController login failed, no token');
        }

        if ($request->wantsJson()) {
            return response()->json(['error' => self::CREDENTIALS_REJECTED_MESSAGE], 401);
        }

        return back()->withErrors([
            'email' => self::CREDENTIALS_REJECTED_MESSAGE,
        ]);
    }

    /**
     * Falls back to a generic referral when no display name is configured, so the sentence still
     * reads and still tells the client to ask someone. A self-hosted portal that never set the
     * value would otherwise end mid-sentence on the one screen that has to be legible.
     */
    private function serviceUnavailableMessage()
    {
        $organizationName = config('portal.organization_display_name');

        return sprintf(
            self::SERVICE_UNAVAILABLE_MESSAGE,
            filled($organizationName) ? $organizationName : self::UNNAMED_ORGANIZATION
        );
    }

    public function getRegisterOptions()
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController getRegisterOptions called');
        }
        $token = Session::get('api_token');
        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $options = $this->apiService->getRegisterOptions($token);
        if ($options) {
            Session::put('registration_challenge', $options['challenge']);
            return response()->json($options);
        }

        return response()->json(['error' => 'Unable to get registration options'], 400);
    }

    public function completeRegistration(Request $request)
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController completeRegistration called');
        }
        $token = Session::get('api_token');
        $challenge = Session::get('registration_challenge');
        if (!$token || !$challenge) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $success = $this->apiService->completeRegistration($token, $challenge, $request->all());
        if ($success) {
            Session::forget('registration_challenge');
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Registration failed'], 400);
    }

    public function getAssertionOptions(Request $request)
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController getAssertionOptions called', ['email' => $request->email]);
        }
        $request->validate(['email' => 'required|email']);
        $email = $request->input('email');

        $options = $this->apiService->getAssertionOptions($email);
        if ($options) {
            Session::put('assertion_challenge', $options['challenge']);
            Session::put('assertion_email', $email);
            return response()->json($options);
        }

        return response()->json(['error' => 'Unable to get assertion options'], 400);
    }

    public function completeAssertion(Request $request)
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController completeAssertion called');
        }
        $challenge = Session::get('assertion_challenge');
        $email = Session::get('assertion_email');
        if (!$challenge || !$email) {
            return response()->json(['error' => 'Invalid session'], 400);
        }

        $token = $this->apiService->completeAssertion($email, $challenge, $request->all());
        if ($token) {
            Session::put('api_token', $token);
            Session::put('user_email', $email);
            Session::forget(['assertion_challenge', 'assertion_email']);
            return response()->json(['token' => $token]);
        }

        return response()->json(['error' => 'Assertion failed'], 400);
    }

    public function logout()
    {
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::debug('AuthController logout called');
        }
        Session::forget(['api_token', 'user_email']);
        return redirect()->route('login');
    }
}
