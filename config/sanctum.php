<?php

return [
  'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
      '%s%s%s',
      'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1,vueshopfim.test',
      env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : '',
      env('FRONTEND_URL') ? ','.parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : ''
  ))),

  'guard' => ['web'], // 🔥 MANTER: Voltar para web guard

  'expiration' => null,

  'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

  'middleware' => [
      'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
      'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
      'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
  ],
];
