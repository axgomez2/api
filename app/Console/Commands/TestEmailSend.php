<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClientUser;
use App\Notifications\ClientVerifyEmail;
use Illuminate\Support\Facades\Log;

class TestEmailSend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email sending for a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $user = ClientUser::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found");
            return 1;
        }

        $this->info("Sending email to: {$user->email}");

        try {
            Log::info('Teste manual de email iniciado', ['user_id' => $user->id, 'email' => $user->email]);

            $user->notify(new ClientVerifyEmail());

            $this->info('Email sent successfully!');

            Log::info('Teste manual de email finalizado com sucesso', ['user_id' => $user->id]);

        } catch (\Exception $e) {
            $this->error('Error sending email: ' . $e->getMessage());
            Log::error('Erro no teste manual de email', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
            ]);
            return 1;
        }

        return 0;
    }
}
