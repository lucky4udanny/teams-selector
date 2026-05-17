<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to join '.$this->invitation->organization->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'acceptUrl' => route('invitations.show', $this->invitation->token),
                'orgName' => $this->invitation->organization->name,
                'inviterName' => $this->invitation->invitedBy->name,
                'role' => $this->invitation->role,
                'expiresAt' => $this->invitation->expires_at->format('F j, Y'),
            ],
        );
    }
}
