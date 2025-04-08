# Set up a CRON Job for automation commands

This guide explains how to set up a **cron job** in Ubuntu to automatically run the following Symfony command daily:

```bash
php bin/console clear:deleteUnconfirmedUsers
```

## Step 1: Open the Crontab File
Run the following command to open the cron scheduler for your user:

```bash
crontab -e
```

## Step 2: Add the Cron Job
In the file that opens, add this line to schedule the command to run daily at midnight:

```bash
0 0 * * * /usr/bin/php /path/to/your/project/bin/console clear:deleteUnconfirmedUsers >> /var/log/clear_unconfirmed_users.log 2>&1
```

- **`/usr/bin/php`**: Replace with the full path to your PHP binary (find it using `which php`).
- **`/path/to/your/project`**: Replace with the actual path to your Symfony project.
- The command directs the output to `/var/log/clear_unconfirmed_users.log` for logging purposes.

## Step 3: Save and Exit
Save and close the crontab file. This will activate the new cron job.

## Step 4: Verify the Cron Job
To confirm the cron job is scheduled, list all active cron jobs with:

```bash
crontab -l
```

You should see the line you added.

## Step 5: Test the Command
To make sure everything works as expected, manually run the command:

```bash
php /path/to/your/project/bin/console clear:deleteUnconfirmedUsers
```

If it runs successfully, your cron job is properly set up.

---

Your command will now automatically run every day at midnight. If you need to change the schedule, modify the timing configuration (`0 0 * * *`) in the crontab:

- `0 3 * * *`: Runs daily at 3:00 AM.
- `30 2 * * *`: Runs daily at 2:30 AM.

Happy automating!
