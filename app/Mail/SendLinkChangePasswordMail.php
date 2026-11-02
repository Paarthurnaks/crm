<?php

namespace App\Mail;

use App\Enums\DefaultRolesEnum;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendLinkChangePasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $link;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code, User $user)
    {
        if ($user->hasRole(DefaultRolesEnum::PARTNER->value) || $user->hasRole(DefaultRolesEnum::CLIENT->value)) {
            $this->link = config('app.partner_create_password_url').'?code='.$code.'&email='.$user->email;
        } else {
            $this->link = config('app.client_create_password_url').'?code='.$code.'&email='.$user->email;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CRM: Сброс пароля',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email.send_link_change_password',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
