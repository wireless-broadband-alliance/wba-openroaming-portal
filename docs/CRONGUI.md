# Set up a CRON Job for automation commands

**Cron Job** is simply a task you schedule to run automatically at specific times. You define these tasks in a file
called the **crontab** (short for "cron table"). Once set up, the portal takes care of running the tasks for you at the
exact times you specify, so you don’t have to do it manually.

In this guide, we’ll focus on setting up a cron job specifically for running a Symfony command automatically. This will
allow the system to handle the task for you at the scheduled time, ensuring the process runs consistently without manual
intervention.

```bash
php bin/console clear:deleteUnconfirmedUsers
php bin/console notify:usersWhenProfileExpires
```

## Step 1: Access the Container and Install Cron

To set up cron, you need to have it installed inside the container we, responsible for the portal. Follow
the steps below:

1**Install Package Cron**:
Once inside the container, install the cron package:

   ```bash
   sudo apt update && apt install -y cron
   ```

---

## Step 2: Open the Crontab File in the root folder

Once cron is installed, you can open the make sure you are on the root folder of the project to execute the following
commands

1. **Open Crontab**:
   Run the following command to open the cron scheduler within the container:
   ```bash
   crontab -e
   ```

2. **Add the Cron Job**:
   In the crontab editor, define the cron job:
   TODO: make a pretty explanation for dumb people to change the example on the command to the location folder of the project
   ```bash
   0 0 * * * /usr/bin/php <project_location>(example: ":~/openroaming-provisioning-web/") php bin/console clear:deleteUnconfirmedUsers >> <project_location>(example: ":~/openroaming-provisioning-web/")/var/log/clear_unconfirmed_users.log 2>&1
   0 0 * * * /usr/bin/php <project_location>(example: ":~/openroaming-provisioning-web/") php bin/console notify:usersWhenProfileExpires >> <project_location>(example: ":~/openroaming-provisioning-web/")/var/log/notify_users.log 2>&1
   ```

### Explanation of the Cron Job:

- **`0 0 * * *`**: Schedules the command to run **daily at midnight**.
- **`/usr/bin/php`**: Specifies the PHP binary to execute the Symfony console commands (use `which php` to verify the
  exact path to PHP in your environment).
- **`php bin/console`**: Executes the Symfony console commands defined in the project.
- **`clear:deleteUnconfirmedUsers`**: A Symfony command to delete unconfirmed users from the system.
- **`notify:usersWhenProfileExpires`**: A Symfony command to notify users when their profile is about to expire.
- **`>> /var/www/openroaming/var/log/clear_unconfirmed_users.log`** or *
  *`>> /var/www/openroaming/var/log/notify_users.log`**:
    - Redirects the output of each specific command to log files for monitoring purposes.
    - Standard output of the command is appended to the respective log file.
    - All error messages (`stderr`) are also redirected to the same log file due to `2>&1`.

---

## Step 3: Enable and Start the Cron Service in the Container

After setting up the cron job, you need to enable and start the cron service to ensure it runs.

1. **Start the Cron Service**:
   Start the cron service inside the container:
   ```bash
   service cron start
   ```

2. **Verify Cron Service Is Running**:
   Optionally, confirm that the cron service is active within the container:
   ```bash
   service cron status
   ```

---

## Step 4: Check Scheduled Cron Jobs in the Container

To verify that your cron job is added and scheduled properly, list the active cron jobs:

```bash
crontab -l
```

You should see the command you added in Step 2.

---

## Step 5: Test the portal automation Commands Manually

Before relying on cron to execute the commands, manually test if portal execution works as expected:

```bash
php bin/console clear:deleteUnconfirmedUsers
php bin/console notify:usersWhenProfileExpires
```

If the command executes successfully, the cron job is ready to run at the scheduled time.
