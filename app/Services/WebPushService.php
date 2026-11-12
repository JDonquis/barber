<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    /**
     * Send a push notification to every device registered for the customer.
     *
     * @return array{sent: int, failed: int, removed: int, errors: array<int, string>}
     */
    public function send(Customer $customer, array $payload): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'removed' => 0, 'errors' => []];

        $subscriptions = PushSubscription::withoutGlobalScope('company')
            ->where('customer_id', $customer->id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return $result;
        }

        if (! $this->isConfigured()) {
            $result['errors'][] = 'Las claves VAPID no están configuradas.';

            return $result;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ],
            ], ['TTL' => 86400]);

            foreach ($subscriptions as $row) {
                try {
                    $subscription = new Subscription(
                        $row->endpoint,
                        $row->p256dh,
                        $row->auth,
                        $row->content_encoding ?: 'aesgcm',
                    );

                    $webPush->queueNotification($subscription, json_encode($payload));
                } catch (Throwable $e) {
                    $result['failed']++;
                    $result['errors'][] = $e->getMessage();
                }
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $result['sent']++;

                    continue;
                }

                $result['failed']++;
                $result['errors'][] = $report->getReason();

                if ($report->isSubscriptionExpired()) {
                    PushSubscription::withoutGlobalScope('company')
                        ->where('endpoint_hash', hash('sha256', $report->getEndpoint()))
                        ->delete();
                    $result['removed']++;
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }
}
