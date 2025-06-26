<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClientUser;
use Laravel\Sanctum\PersonalAccessToken;

class TestTokenCreation extends Command
{
    protected $signature = 'test:token-creation {email}';
    protected $description = 'Testa a criação de tokens para um usuário';

    public function handle()
    {
        $email = $this->argument('email');
        
        $user = ClientUser::where('email', $email)->first();
        
        if (!$user) {
            $this->error("Usuário com email {$email} não encontrado");
            return 1;
        }
        
        $this->info("Testando criação de token para: {$user->name} ({$user->email})");
        
        // Limpar tokens antigos
        $user->tokens()->delete();
        $this->info("Tokens antigos removidos");
        
        // Criar novo token
        $tokenResult = $user->createToken('test-token');
        $token = $tokenResult->plainTextToken;
        
        $this->info("Token criado: " . substr($token, 0, 20) . "...");
        $this->info("Token ID: " . $tokenResult->accessToken->id);
        
        // Verificar se o token pode ser encontrado
        $foundToken = PersonalAccessToken::findToken($token);
        
        if ($foundToken) {
            $this->info("✅ Token encontrado na base de dados");
            $this->info("Tokenable Type: " . $foundToken->tokenable_type);
            $this->info("Tokenable ID: " . $foundToken->tokenable_id);
        } else {
            $this->error("❌ Token não encontrado na base de dados");
        }
        
        return 0;
    }
}
