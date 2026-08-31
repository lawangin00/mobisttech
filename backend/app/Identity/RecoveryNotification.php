<?php

namespace App\Identity;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecoveryNotification extends Notification
{
    public function __construct(public string $realm, public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $origin = config('identity.'.($this->realm === 'customer' ? 'customer' : 'admin').'_origin');
        $path = $this->realm === 'customer' ? '/reset-password' : '/internal/'.$this->realm.'/reset-password';
        $url = $origin.$path.'?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)->subject('Reset your mobiST Tech password')->line('This link expires in 60 minutes.')
            ->action('Reset password', $url)->line('If you did not request this, no action is needed.');
    }
}
