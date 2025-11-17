<?php

namespace App\Listeners;

use App\Events\WithdrawCompleted;
use Illuminate\Support\Facades\Log;

class SendNotificationOnWithdrawCompleted
{
    /**
     * Handle the event.
     */
    public function handle(WithdrawCompleted $event): void
    {
        $withdraw = $event->withdraw;

        Log::info('Withdraw completed', [
            'withdraw_id' => $withdraw->id,
            'user_id' => $withdraw->user_id,
            'amount' => $withdraw->amount,
            'external_id' => $withdraw->external_withdraw_id,
        ]);

        // Here you could send notifications, emails, etc.
        // Example: Notification::send($withdraw->user, new WithdrawCompletedNotification($withdraw));
    }
}

