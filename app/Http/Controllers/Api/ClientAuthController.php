<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\ClientUser;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use App\Notifications\ClientVerifyEmail;

class ClientAuthController extends Controller
{
    use ApiResponse;
  public function register(RegisterRequest $request)
  {
      try {
          $data = $request->validated();

          \Log::info('Tentativa de registro:', [
              'email' => $data['email'],
              'name' => $data['name']
          ]);

          $user = ClientUser::create([
              'name' => $data['name'],
              'email' => $data['email'],
              'password' => Hash::make($data['password']),
          ]);

          // Enviar email de verificação
          $user->sendEmailVerificationNotification();

          // 🔥 MELHORAR: Criar token com nome mais específico e verificar se foi criado
          $tokenResult = $user->createToken('client-auth-token');
          $token = $tokenResult->plainTextToken;

          // 🔥 ADICIONAR: Verificar se o token foi criado corretamente
          if (!$token) {
              \Log::error('Falha ao criar token para usuário:', ['user_id' => $user->id]);
              return $this->serverErrorResponse('Erro ao criar token de autenticação');
          }

          \Log::info('Registro bem-sucedido:', [
              'user_id' => $user->id,
              'email' => $user->email,
              'token_id' => $tokenResult->accessToken->id,
              'token_preview' => substr($token, 0, 20) . '...',
              'token_length' => strlen($token)
          ]);

          return $this->createdResponse([
              'token' => $token,
              'user' => new UserResource($user)
          ], 'Conta criada com sucesso!');

      } catch (\Exception $e) {
          \Log::error('Erro no registro:', [
              'message' => $e->getMessage(),
              'file' => $e->getFile(),
              'line' => $e->getLine(),
              'trace' => $e->getTraceAsString()
          ]);

          return $this->serverErrorResponse('Erro interno do servidor');
      }
  }

  public function login(LoginRequest $request)
  {
      try {

          \Log::info('Tentativa de login:', ['email' => $request->email]);

          $user = ClientUser::where('email', $request->email)->first();

          if (!$user || !Hash::check($request->password, $user->password)) {
              \Log::warning('Login falhou:', ['email' => $request->email]);
              return $this->unauthorizedResponse('Credenciais inválidas');
          }

          // 🔥 MELHORAR: Criar token com nome mais específico
          $tokenResult = $user->createToken('client-auth-token');
          $token = $tokenResult->plainTextToken;

          // 🔥 ADICIONAR: Verificar se o token foi criado corretamente
          if (!$token) {
              \Log::error('Falha ao criar token para usuário:', ['user_id' => $user->id]);
              return $this->serverErrorResponse('Erro ao criar token de autenticação');
          }

          \Log::info('Login bem-sucedido:', [
              'user_id' => $user->id,
              'email' => $user->email,
              'token_id' => $tokenResult->accessToken->id,
              'token_preview' => substr($token, 0, 20) . '...',
              'token_length' => strlen($token)
          ]);

          return $this->successResponse([
              'token' => $token,
              'user' => new UserResource($user)
          ], 'Login realizado com sucesso!');

      } catch (\Exception $e) {
          \Log::error('Erro no login:', [
              'message' => $e->getMessage(),
              'file' => $e->getFile(),
              'line' => $e->getLine(),
              'trace' => $e->getTraceAsString()
          ]);

          return $this->serverErrorResponse('Erro interno do servidor');
      }
  }

  public function me(Request $request)
  {
      try {
          // 🔥 O usuário já foi definido pelo middleware
          $user = $request->user();

          if (!$user) {
              return $this->unauthorizedResponse('Usuário não autenticado');
          }

          \Log::info('Dados do usuário solicitados:', [
              'user_id' => $user->id,
              'email' => $user->email
          ]);

          return $this->successResponse([
              'user' => new UserResource($user)
          ]);

      } catch (\Exception $e) {
          \Log::error('Erro ao buscar usuário:', [
              'message' => $e->getMessage(),
              'trace' => $e->getTraceAsString()
          ]);

          return $this->serverErrorResponse('Erro interno do servidor');
      }
  }

  /**
   * Atualizar perfil do usuário
   */
  public function updateProfile(Request $request)
  {
      try {
          $user = $request->user();

          $validator = Validator::make($request->all(), [
              'name' => 'sometimes|required|string|max:255',
              'phone' => 'sometimes|nullable|string|max:20',
              'cpf' => 'sometimes|nullable|string|max:14|unique:client_users,cpf,' . $user->id,
              'birth_date' => 'sometimes|nullable|date|before:today',
          ]);

          if ($validator->fails()) {
              return response()->json([
                  'message' => 'Dados inválidos',
                  'errors' => $validator->errors()
              ], 422);
          }

          $data = $validator->validated();

          $user->update($data);

          return response()->json([
              'success' => true,
              'message' => 'Perfil atualizado com sucesso!',
              'user' => [
                  'id' => $user->id,
                  'name' => $user->name,
                  'email' => $user->email,
                  'phone' => $user->phone,
                  'cpf' => $user->cpf,
                  'birth_date' => $user->birth_date,
                  'email_verified_at' => $user->email_verified_at,
                  'formatted_cpf' => $user->formatted_cpf,
                  'formatted_phone' => $user->formatted_phone,
              ]
          ], 200);

      } catch (\Exception $e) {
          \Log::error('Erro ao atualizar perfil:', [
              'message' => $e->getMessage(),
              'user_id' => $request->user()->id ?? 'unknown'
          ]);

          return response()->json([
              'success' => false,
              'message' => 'Erro interno do servidor',
              'error' => $e->getMessage()
          ], 500);
      }
  }

  /**
   * Alterar senha
   */
  public function changePassword(Request $request)
  {
      try {
          $user = $request->user();

          $validator = Validator::make($request->all(), [
              'current_password' => 'required',
              'password' => 'required|min:6|confirmed',
          ]);

          if ($validator->fails()) {
              return response()->json([
                  'message' => 'Dados inválidos',
                  'errors' => $validator->errors()
              ], 422);
          }

          if (!Hash::check($request->current_password, $user->password)) {
              return response()->json([
                  'message' => 'Senha atual incorreta'
              ], 422);
          }

          $user->update([
              'password' => Hash::make($request->password)
          ]);

          return response()->json([
              'success' => true,
              'message' => 'Senha alterada com sucesso!'
          ], 200);

      } catch (\Exception $e) {
          \Log::error('Erro ao alterar senha:', [
              'message' => $e->getMessage(),
              'user_id' => $request->user()->id ?? 'unknown'
          ]);

          return response()->json([
              'success' => false,
              'message' => 'Erro interno do servidor',
              'error' => $e->getMessage()
          ], 500);
      }
  }

  /**
   * Reenviar email de verificação
   */
  public function resendVerificationEmail(Request $request)
  {
      try {
          $user = $request->user();
          Log::info('Iniciando reenvio de email de verificação', [
              'user_id' => $user->id,
              'email' => $user->email,
              'email_verified_at' => $user->email_verified_at
          ]);

          if ($user->hasVerifiedEmail()) {
              Log::info('Email já verificado', ['user_id' => $user->id]);
              return response()->json([
                  'message' => 'Email já foi verificado.'
              ], 400);
          }

          Log::info('Tentando enviar notificação de verificação', ['user_id' => $user->id]);

          // Tentar enviar a notificação
          $user->notify(new ClientVerifyEmail());

          Log::info('Notificação enviada com sucesso', ['user_id' => $user->id]);

          return response()->json([
              'message' => 'Email de verificação enviado com sucesso!'
          ]);

      } catch (\Exception $e) {
          Log::error('Erro ao reenviar email de verificação', [
              'message' => $e->getMessage(),
              'trace' => $e->getTraceAsString(),
              'user_id' => $request->user()->id ?? null
          ]);

          return response()->json([
              'message' => 'Erro ao enviar email de verificação.',
              'error' => $e->getMessage()
          ], 500);
      }
  }

  public function logout(Request $request)
  {
      try {
          $user = $request->user();

          if ($user) {
              // 🔥 Deletar apenas o token atual
              $token = $request->bearerToken();
              if ($token) {
                  $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                  if ($accessToken) {
                      $accessToken->delete();
                  }
              }
          }

          \Log::info('Logout realizado:', ['user_id' => $user?->id]);

          return response()->json(['message' => 'Logout efetuado com sucesso'], 200);

      } catch (\Exception $e) {
          \Log::error('Erro no logout:', [
              'message' => $e->getMessage(),
              'trace' => $e->getTraceAsString()
          ]);

          return response()->json([
              'message' => 'Erro interno do servidor',
              'error' => $e->getMessage()
          ], 500);
      }
  }

  /**
   * Verificar email do usuário
   */
  public function verifyEmail(Request $request, $id, $hash)
  {
      try {
          $user = ClientUser::findOrFail($id);

          Log::info('Verificação de email iniciada', [
              'user_id' => $id,
              'hash' => $hash,
              'email' => $user->email
          ]);

          if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
              Log::warning('Hash de verificação inválido', ['user_id' => $id]);
              $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
              return redirect($frontendUrl . '/email-verified?success=false&message=Link de verificação inválido');
          }

          if ($user->hasVerifiedEmail()) {
              Log::info('Email já estava verificado', ['user_id' => $id]);
              $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
              return redirect($frontendUrl . '/email-verified?success=true&message=Email já verificado&already_verified=true');
          }

          if ($user->markEmailAsVerified()) {
              event(new \Illuminate\Auth\Events\Verified($user));

              Log::info('Email verificado com sucesso', ['user_id' => $id]);

              $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
              return redirect($frontendUrl . '/email-verified?success=true&message=Email verificado com sucesso!');
          }

          Log::error('Falha ao marcar email como verificado', ['user_id' => $id]);
          $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
          return redirect($frontendUrl . '/email-verified?success=false&message=Erro ao verificar email');

      } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
          Log::error('Usuário não encontrado na verificação', ['user_id' => $id]);
          $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
          return redirect($frontendUrl . '/email-verified?success=false&message=Usuário não encontrado');
      } catch (\Exception $e) {
          Log::error('Erro na verificação de email', [
              'message' => $e->getMessage(),
              'user_id' => $id ?? 'unknown',
              'trace' => $e->getTraceAsString()
          ]);

          $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
          return redirect($frontendUrl . '/email-verified?success=false&message=Erro interno do servidor');
      }
  }

  /**
   * Redireciona o usuário para a página de autenticação do Google.
   */
  public function redirectToGoogle()
  {
      return Socialite::driver('google')->stateless()->redirect();
  }

  /**
   * Obtém as informações do usuário do Google e lida com o login/registro.
   */
  public function handleGoogleCallback()
  {
      try {
          $googleUser = Socialite::driver('google')->stateless()->user();

          \Log::info('Google OAuth callback:', [
              'email' => $googleUser->getEmail(),
              'name' => $googleUser->getName(),
              'google_id' => $googleUser->getId()
          ]);

          $user = ClientUser::updateOrCreate(
              ['email' => $googleUser->getEmail()],
              [
                  'name' => $googleUser->getName(),
                  'google_id' => $googleUser->getId(),
                  'password' => Hash::make(Str::random(24)), // Senha aleatória
                  'email_verified_at' => now(), // Marcar como verificado
              ]
          );

          // Forçar atualização do email_verified_at se ainda não estiver definido
          if (!$user->email_verified_at) {
              $user->email_verified_at = now();
              $user->save();
              \Log::info('email_verified_at forçadamente atualizado para usuário:', ['user_id' => $user->id]);
          }

          \Log::info('Usuário Google OAuth processado:', [
              'user_id' => $user->id,
              'email' => $user->email,
              'email_verified_at' => $user->email_verified_at,
              'google_id' => $user->google_id
          ]);

          $token = $user->createToken('client-auth-token')->plainTextToken;

          // Redirecionar para o frontend com o token
          $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
          return redirect($frontendUrl . '/auth/callback?token=' . $token);

      } catch (\Exception $e) {
          Log::error('Erro no callback do Google:', ['error' => $e->getMessage()]);
          $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
          return redirect($frontendUrl . '/login?error=google_login_failed');
      }
  }
}
