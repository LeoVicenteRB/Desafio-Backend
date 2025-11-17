<?php

namespace App\Listeners;

use App\Events\PixConfirmed;
use Illuminate\Support\Facades\Log;

class SendNotificationOnPixConfirmed
{
    /**
     * Handle the event.
     */
    public function handle(PixConfirmed $event): void
    {
        $pix = $event->pix;

        Log::info('PIX confirmed', [
            'pix_id' => $pix->id,
            'user_id' => $pix->user_id,
            'amount' => $pix->amount,
            'external_id' => $pix->external_pix_id,
        ]);

        // Here you could send notifications, emails, etc.
        // Example: Notification::send($pix->user, new PixConfirmedNotification($pix));
    }
}

