<?php

namespace App\Services;

use App\Mail\NotificationEmail;
use App\Models\Notification;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private MailSettingsService $mailSettings,
    ) {}

    public function send(User $user, string $type, string $title, string $body = '', ?string $link = null): Notification
    {
        $notification = Notification::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
        ]);

        $this->sendEmailIfEnabled($user, $title, $body, $link);

        return $notification;
    }

    /** Email sortant si SMTP tenant configuré ET option « notifications par email » cochée. */
    private function sendEmailIfEnabled(User $user, string $title, string $body, ?string $link): void
    {
        $tenant = $user->tenant;
        if (! $tenant) {
            return;
        }

        $settings = $tenant->settings ?? [];
        if (! ($settings['mail_enabled'] ?? false) || ! ($settings['email_notifications'] ?? false)) {
            return;
        }

        $previous = TenantContext::get();
        TenantContext::set($tenant->id);

        try {
            $this->mailSettings->configure($tenant);
            Mail::to($user->email)->send(new NotificationEmail(
                title: $title,
                body: $body,
                link: $link,
                appName: $this->mailSettings->fromName($tenant),
            ));
        } catch (\Throwable) {
            // L'échec d'envoi ne doit jamais bloquer la notification en base.
        } finally {
            TenantContext::set($previous);
        }
    }
}
