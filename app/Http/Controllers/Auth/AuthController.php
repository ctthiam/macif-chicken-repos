<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Jobs\SendEmailVerificationJob;
use App\Jobs\SendPasswordResetJob;
use App\Models\AcheteurProfile;
use App\Models\EleveurProfile;
use App\Models\User;
use App\Services\StorageService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Contrôleur d'authentification MACIF CHICKEN
 *
 * Fichier : app/Http/Controllers/Auth/AuthController.php
 */
class AuthController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private readonly StorageService $storageService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // AUTH-01 / AUTH-02 — Inscription
    // POST /api/auth/register
    // ══════════════════════════════════════════════════════════════

    /**
     * Inscrit un nouvel utilisateur (éleveur ou acheteur).
     *
     * @param  RegisterRequest $request
     * @return JsonResponse  201 | 500
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name'                     => $request->name,
                    'email'                    => $request->email,
                    'password'                 => Hash::make($request->password),
                    'phone'                    => $request->phone,
                    'role'                     => $request->role,
                    'ville'                    => $request->ville,
                    'adresse'                  => $request->adresse,
                    'is_verified'              => app()->environment('local'), // true en local, false en prod
                    'is_active'                => true,
                    'email_verification_token' => Str::random(64),
                ]);

                if ($request->role === 'eleveur') {
                    EleveurProfile::create([
                        'user_id'        => $user->id,
                        'nom_poulailler' => $request->nom_poulailler,
                        'description'    => $request->description,
                        'localisation'   => $request->localisation,
                    ]);
                    $user->load('eleveurProfile');
                }

                if ($request->role === 'acheteur') {
                    AcheteurProfile::create([
                        'user_id'           => $user->id,
                        'type'              => $request->type,
                        'nom_etablissement' => $request->nom_etablissement,
                        'ninea'             => $request->ninea,
                    ]);
                    $user->load('acheteurProfile');
                }

                $user->assignRole($request->role);
                return $user;
            });

            SendEmailVerificationJob::dispatch($user);

            // Notifier l'admin du nouvel utilisateur
            $admins = \App\Models\User::where('role', 'admin')->pluck('id')->toArray();
            if (!empty($admins)) {
                $roleLabel = $user->role === 'eleveur' ? 'éleveur' : 'acheteur';
                $this->notificationService->notifierMultiple(
                    userIds: $admins,
                    titre:   '👤 Nouvel utilisateur',
                    message: $user->name . " vient de s'inscrire en tant que " . $roleLabel . ".",
                    type:    'system',
                    data:    ['user_id' => $user->id, 'role' => $user->role]
                );
            }
            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie. Veuillez vérifier votre email pour activer votre compte.',
                'data'    => new UserResource($user),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'inscription.',
                'errors'  => config('app.debug') ? ['exception' => $e->getMessage()] : [],
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-03 — Vérification email
    // GET /api/auth/verify-email/{token}
    // ══════════════════════════════════════════════════════════════

    /**
     * Vérifie l'email via le token. Expire 24h. Usage unique.
     *
     * @param  string $token
     * @return JsonResponse  200 | 400 | 422
     */
    public function verifyEmail(string $token): JsonResponse
    {
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Lien de vérification invalide ou déjà utilisé.', 'errors' => []], 400);
        }

        if ($user->is_verified) {
            return response()->json(['success' => false, 'message' => 'Votre adresse email est déjà vérifiée.', 'errors' => []], 422);
        }

        if ($user->created_at->addHours(24)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce lien de vérification a expiré. Veuillez en demander un nouveau.',
                'errors'  => ['token' => 'Expiré depuis le ' . $user->created_at->addHours(24)->format('d/m/Y à H:i')],
            ], 400);
        }

        $user->update([
            'is_verified'              => true,
            'email_verified_at'        => now(),
            'email_verification_token' => null,
        ]);

        if ($user->role === 'eleveur') $user->load('eleveurProfile');
        elseif ($user->role === 'acheteur') $user->load('acheteurProfile');

        return response()->json([
            'success' => true,
            'message' => 'Adresse email vérifiée avec succès ! Vous pouvez maintenant vous connecter.',
            'data'    => new UserResource($user),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-04 — Connexion
    // POST /api/auth/login
    // ══════════════════════════════════════════════════════════════

    /**
     * Connecte un utilisateur. Vérifie is_active, is_verified.
     * Retourne token dans body + cookie httpOnly 7 jours.
     *
     * @param  LoginRequest $request
     * @return JsonResponse  200 | 401 | 403
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json(['success' => false, 'message' => 'Email ou mot de passe incorrect.', 'errors' => []], 401);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['success' => false, 'message' => 'Votre compte a été suspendu. Contactez l\'administration.', 'errors' => []], 403);
        }

        if (!$user->is_verified) {
            Auth::logout();
            return response()->json(['success' => false, 'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.', 'errors' => ['email' => 'Email non vérifié.']], 403);
        }

        if ($user->role === 'eleveur') $user->load('eleveurProfile');
        elseif ($user->role === 'acheteur') $user->load('acheteurProfile');

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'data'    => ['user' => new UserResource($user), 'token' => $token],
        ], 200)->withCookie(cookie('api_token', $token, 60 * 24 * 7, '/', null, app()->isProduction(), true, false, 'Lax'));
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-05 — Déconnexion
    // POST /api/auth/logout
    // ══════════════════════════════════════════════════════════════

    /**
     * Révoque tous les tokens et expire le cookie.
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
            'data'    => null,
        ], 200)->withCookie(cookie()->forget('api_token'));
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-06 — Profil connecté
    // GET /api/auth/me
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne le profil complet de l'utilisateur connecté.
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role === 'eleveur') $user->load('eleveurProfile');
        elseif ($user->role === 'acheteur') $user->load('acheteurProfile');

        return response()->json([
            'success' => true,
            'message' => 'Profil récupéré avec succès.',
            'data'    => new UserResource($user),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-07 — Modifier profil
    // PUT /api/auth/profile
    // ══════════════════════════════════════════════════════════════

    /**
     * Met à jour le profil (name, phone, ville, adresse, avatar).
     * Avatar uploadé sur R2 via StorageService.
     *
     * @param  UpdateProfileRequest $request
     * @return JsonResponse  200 | 422
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user   = $request->user();
        $fields = [];

        if ($request->filled('name'))    $fields['name']    = $request->name;
        if ($request->filled('phone'))   $fields['phone']   = $request->phone;
        if ($request->has('ville'))      $fields['ville']   = $request->ville;
        if ($request->has('adresse'))    $fields['adresse'] = $request->adresse;

        if ($request->hasFile('avatar')) {
            $fields['avatar'] = $this->storageService->uploadAvatar(
                $request->file('avatar'),
                $user->avatar
            );
        }

        if (!empty($fields)) $user->update($fields);

        if ($user->role === 'eleveur') $user->load('eleveurProfile');
        elseif ($user->role === 'acheteur') $user->load('acheteurProfile');

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'data'    => new UserResource($user->fresh()),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-CP — Changement de mot de passe (utilisateur connecté)
    // PUT /api/auth/change-password
    // ══════════════════════════════════════════════════════════════

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect.',
                'errors'  => ['current_password' => ['Mot de passe incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-08 — Demande de réinitialisation
    // POST /api/auth/forgot-password
    // ══════════════════════════════════════════════════════════════

    /**
     * Génère un token de reset et envoie l'email.
     *
     * Sécurité : on retourne toujours le même message succès,
     * que l'email existe ou non (évite l'énumération d'utilisateurs).
     * Le token expire dans 1 heure.
     *
     * @param  ForgotPasswordRequest $request
     * @return JsonResponse  200 (toujours)
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // Réponse identique que l'email existe ou non (anti-énumération)
        $message = 'Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation dans quelques minutes.';

        if (!$user) {
            return response()->json(['success' => true, 'message' => $message, 'data' => null], 200);
        }

        // ── Générer un token unique valable 1h ───────────────────────
        $token   = Str::random(64);
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            . '/auth/reset-password?token=' . $token
            . '&email=' . urlencode($user->email);

        $user->update([
            'password_reset_token'      => $token,
            'password_reset_expires_at' => now()->addHour(),
        ]);

        // ── Dispatcher le job d'envoi email ──────────────────────────
        SendPasswordResetJob::dispatch($user, $resetUrl);

        return response()->json(['success' => true, 'message' => $message, 'data' => null], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH-08 — Réinitialisation du mot de passe
    // POST /api/auth/reset-password
    // ══════════════════════════════════════════════════════════════

    /**
     * Réinitialise le mot de passe avec le token reçu par email.
     *
     * Vérifications :
     *  1. Email + token correspondent à un utilisateur
     *  2. Token non expiré (1h)
     *  3. Nouveau mot de passe ≠ token (sécurité de base)
     * Après succès : efface le token + révoque tous les tokens Sanctum.
     *
     * @param  ResetPasswordRequest $request
     * @return JsonResponse  200 | 400 | 422
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        // ── 1. Trouver le user par email + token ────────────────────
        $user = User::where('email', $request->email)
            ->where('password_reset_token', $request->token)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token de réinitialisation invalide ou email incorrect.',
                'errors'  => [],
            ], 400);
        }

        // ── 2. Vérifier l'expiration (1h) ───────────────────────────
        if (!$user->isPasswordResetTokenValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce lien de réinitialisation a expiré. Veuillez en demander un nouveau.',
                'errors'  => [],
            ], 400);
        }

        // ── 3. Mettre à jour le mot de passe ────────────────────────
        $user->update([
            'password'                  => Hash::make($request->password),
            'password_reset_token'      => null, // invalider le token
            'password_reset_expires_at' => null,
        ]);

        // ── 4. Révoquer tous les tokens Sanctum (sécurité) ──────────
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
            'data'    => null,
        ], 200);
    }
}