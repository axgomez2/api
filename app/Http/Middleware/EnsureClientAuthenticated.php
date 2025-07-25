<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken; // 🔥 VOLTAR: Usar modelo padrão
use App\Models\ClientUser;

class EnsureClientAuthenticated
{
  /**
   * Handle an incoming request.
   * 🔥 VOLTAR: Para a versão que estava funcionando
   */
  public function handle(Request $request, Closure $next): Response
  {
      $token = $request->bearerToken();
      
      \Log::info('🔑 [Middleware] Processando requisição:', [
          'url' => $request->url(),
          'method' => $request->method(),
          'has_token' => !empty($token),
          'token_preview' => $token ? substr($token, 0, 20) . '...' : 'null',
          'user_agent' => $request->userAgent()
      ]);
      
      if (!$token) {
          \Log::warning('❌ [Middleware] Token ausente na requisição', [
              'url' => $request->url(),
              'method' => $request->method(),
              'authorization_header' => $request->header('Authorization'),
              'all_headers' => $request->headers->all()
          ]);
          return response()->json(['message' => 'Token não fornecido'], 401);
      }

      \Log::info('Verificando token:', [
          'token_preview' => substr($token, 0, 20) . '...',
          'token_length' => strlen($token),
          'url' => $request->url()
      ]);

      // 🔥 VOLTAR: Usar modelo padrão do Laravel Sanctum
      $accessToken = PersonalAccessToken::findToken($token);
      
      if (!$accessToken) {
          \Log::warning('Token não encontrado na base de dados:', [
              'token_preview' => substr($token, 0, 20) . '...',
              'url' => $request->url()
          ]);
          return response()->json(['message' => 'Token inválido'], 401);
      }

      \Log::info('Token encontrado:', [
          'token_id' => $accessToken->id,
          'tokenable_type' => $accessToken->tokenable_type,
          'tokenable_id' => $accessToken->tokenable_id,
          'created_at' => $accessToken->created_at
      ]);

      // 🔥 Verificar se o token pertence a um ClientUser
      if ($accessToken->tokenable_type !== ClientUser::class) {
          \Log::warning('Token não pertence a ClientUser:', [
              'tokenable_type' => $accessToken->tokenable_type,
              'expected' => ClientUser::class,
              'token_id' => $accessToken->id
          ]);
          return response()->json(['message' => 'Token inválido para este contexto'], 401);
      }

      // 🔥 Carregar o usuário
      $user = $accessToken->tokenable;
      
      if (!$user) {
          \Log::warning('Usuário não encontrado para o token:', [
              'token_id' => $accessToken->id,
              'tokenable_id' => $accessToken->tokenable_id
          ]);
          return response()->json(['message' => 'Usuário não encontrado'], 401);
      }

      // 🔥 Definir o usuário autenticado na requisição
      $request->setUserResolver(function () use ($user) {
          return $user;
      });

      \Log::info('Usuário autenticado com sucesso:', [
          'user_id' => $user->id,
          'email' => $user->email,
          'token_id' => $accessToken->id
      ]);

      return $next($request);
  }
}
