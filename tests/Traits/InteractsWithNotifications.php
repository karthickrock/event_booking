<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Notification;

trait InteractsWithNotifications
{
    /**
     * Fake notifications for testing
     */
    protected function setUpNotifications(): void
    {
        Notification::fake();
    }

    /**
     * Assert that a notification was sent to a specific user
     */
    protected function assertNotificationSentTo($notifiable, $notificationClass, $callback = null)
    {
        Notification::assertSentTo($notifiable, $notificationClass, $callback);
    }

    /**
     * Assert that a notification was sent a specific number of times
     */
    protected function assertNotificationSent($notificationClass, $count = 1, $callback = null)
    {
        Notification::assertSent($notificationClass, $callback, $count);
    }

    /**
     * Assert that no notifications were sent
     */
    protected function assertNoNotificationsSent()
    {
        Notification::assertNothingSent();
    }

    /**
     * Assert that a notification was not sent to a specific user
     */
    protected function assertNotificationNotSentTo($notifiable, $notificationClass)
    {
        Notification::assertNotSentTo($notifiable, $notificationClass);
    }

    /**
     * Get sent notifications for further inspection
     */
    protected function getSentNotifications()
    {
        return Notification::sent();
    }
}