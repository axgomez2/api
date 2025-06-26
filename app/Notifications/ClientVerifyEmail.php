<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ClientVerifyEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        Log::info('ClientVerifyEmail notification criada');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        Log::info('Via method chamado', ['channels' => ['mail']]);
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        Log::info('toMail method iniciado', [
            'user_id' => $notifiable->id,
            'email' => $notifiable->email
        ]);

        $verificationUrl = $this->verificationUrl($notifiable);

        Log::info('URL de verificação gerada', [
            'user_id' => $notifiable->id,
            'url' => $verificationUrl
        ]);

        $mailMessage = (new MailMessage)
            ->subject('Verificar Email - VueShop')
            ->greeting('Olá!')
            ->line('Por favor, clique no botão abaixo para verificar seu endereço de email.')
            ->action('Verificar Email', $verificationUrl)
            ->line('Se você não criou uma conta, nenhuma ação adicional é necessária.');

        Log::info('MailMessage criada com sucesso', ['user_id' => $notifiable->id]);

        return $mailMessage;
    }

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable)
    {
        Log::info('Gerando URL de verificação', ['user_id' => $notifiable->id]);

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
