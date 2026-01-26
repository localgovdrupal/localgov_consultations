# Localgov Consultations Notify

This submodule provides email notifications for consultations.

## How Emails are Triggered

Emails are sent to subscribers of consultations in the following scenarios:

1.  **Subscription Confirmation**: When a user subscribes to a consultation or all consultations, a confirmation email is sent to them. This is handled by the `SubscriptionHandler` service when a new subscription entity is created.

2.  **Consultation Dates Changed**: When the start or end date of a consultation is changed, an email is sent to all subscribers of that consultation. This is triggered by a `hook_node_update()` implementation in the `.module` file, which calls the `Notifier` service.

3.  **Consultation Opened**: When the consultation status field changes from "upcoming" to "open", an email is sent to all subscribers. This is triggered by a `hook_node_update()` implementation in the `.module` file, which detects the status field change and calls the `Notifier` service.

4.  **Consultation Closed**: When the consultation status field changes from "open" to "closed", an email is sent to all subscribers. This is also triggered by `hook_node_update()`. In addition to notifying subscribers, an email is sent to the contact person for the consultation, prompting them to provide the results.


## Email Queuing

Instead of being sent immediately, emails are added to a queue. The queue is processed during cron runs, or when the queue is processed manually. This ensures that a large number of emails does not slow down the site. The queue worker `EmailQueue` is responsible for sending the emails from the queue.

## Migration from Date-based to Status-based Triggering

Prior to this implementation, consultation opened/closed notifications were triggered by a cron job that queried consultations by date. This has been replaced with a status field change trigger in `hook_node_update()`.
