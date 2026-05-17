<x-mail::message>
# You've been invited to {{ $orgName }}

**{{ $inviterName }}** has invited you to join **{{ $orgName }}** as **{{ $role }}**.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invitation expires on **{{ $expiresAt }}**.

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
