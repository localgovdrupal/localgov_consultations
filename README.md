Localgov Consultations
----------------------

Provides a Consultation content type and view to track public consultations.

## Features

### Automatic Status Updates

Consultations have a status field with the following values:
- **Upcoming**: The consultation has not yet opened
- **Open**: The consultation is currently accepting feedback
- **Closed**: The consultation period has ended
- **Concluded**: The consultation has been reviewed and results published

The module automatically updates consultation statuses based on their start and end dates:
- When the start date is reached, consultations with status "Upcoming" are changed to "Open"
- When the end date is reached, consultations with status "Open" are changed to "Closed"

This process runs during cron, or can be triggered manually using the Drush command:

```bash
drush localgov_consultations:update-statuses
```

### Notifications Sub-module

Included sub-module `localgov_consultations_notify` allows visitors to subscribe for updates about a particular consultation. When a consultation's status changes automatically, email notifications are sent to subscribers.
