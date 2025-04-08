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

1. **Access the Running Container**:
   Run the following command to access the container’s terminal:
   ```bash
   docker exec -it <container_id> bash
   ```
   Replace `<container_id>` with the ID of your running container.

2. **Install Cron Inside the Container**:
   Once inside the container, install the cron package:
   ```bash
   apt-get update && apt-get install -y cron
   ```

---

## Step 2: Open the Crontab File in the Container

Once cron is installed, you can open the container’s crontab file to schedule your tasks.

1. **Open Crontab**:
   Run the following command to open the cron scheduler within the container:
   ```bash
   crontab -e
   ```

2. **Add the Cron Job**:
   In the crontab editor, define the cron job:

   ```bash
   0 0 * * * /usr/bin/php /var/www/openroaming/ php bin/console clear:deleteUnconfirmedUsers >> /var/log/clear_unconfirmed_users.log 2>&1
   0 0 * * * /usr/bin/php /var/www/openroaming/ php bin/console notify:usersWhenProfileExpires >> /var/log/notify_users.log 2>&1
   ```

### Explanation of the Cron Job:

- **`0 0 * * *`**: Specifies the frequency to run – this example schedules the command daily at midnight.
- **`/usr/bin/php`**: Path to the PHP binary inside the container (use `which php` within the container to confirm the
  path).
- **`/path/to/your/project`**: The path to your Symfony project within the container – replace it with the correct
  directory.

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
