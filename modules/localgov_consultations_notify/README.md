# Localgov Consultations Notify

This submodule provides email notifications for consultations.

## How Emails are Triggered

Emails are sent to subscribers of consultations in the following scenarios:

1.  **Subscription Confirmation**: When a user subscribes to a consultation or all consultations, a confirmation email is sent to them. This is handled by the `SubscriptionHandler` service when a new subscription entity is created.

2.  **Consultation Dates Changed**: When the start or end date of a consultation is changed, an email is sent to all subscribers of that consultation. This is triggered by a `hook_node_update()` implementation in the `.module` file, which calls the `Notifier` service.

3.  **Consultation Opened**: The module checks for consultations that have recently opened and sends an email to subscribers. This is performed during cron runs (`hook_cron()`) via the `Notifier` service. This can also be triggered manually by using the `drush localgov_consultations:process-email` command.

4.  **Consultation Closed**: The module checks for consultations that have recently closed and sends an email to subscribers. This is also performed during cron runs. In addition to notifying subscribers, an email is sent to the contact person for the consultation, prompting them to provide the results. 


## Email Queuing

Instead of being sent immediately, emails are added to a queue. The queue is processed during cron runs, or when the queue is processed manually. This ensures that a large number of emails does not slow down the site. The queue worker `EmailQueue` is responsible for sending the emails from the queue.
