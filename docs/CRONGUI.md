# Set up a CRON Job for automation commands

**Cron Job** is simply a task you schedule to run automatically at specific times. You define these tasks in a file
called the **crontab** (short for "cron table"). Once set up, the portal takes care of running the tasks for you at the
exact times you specify, so you don’t have to do it manually.

In this guide, we’ll focus on setting up a cron job specifically for running a Symfony command automatically. This will
allow the system to handle the task for you at the scheduled time, ensuring the process runs consistently without manual
intervention.

```bash
- php bin/console clear:deleteUnconfirmedUsers
- php bin/console notify:usersWhenProfileExpires
```

## Step 1: Identify the Container and Install Cron

Before setting up `cron`, you need to identify and confirm that the container responsible for the portal database is
running.
Once verified, you can proceed to install `cron` inside your system. Follow the steps below:

1. **Identify the Docker Container**:
Run the following command to automatically detect the correct container:

```shell
   container_name=$(docker ps --format '{{.Names}}' | grep "mysql")
   if [ -z "$container_name" ]; then
       echo "Error: No container found matching the keyword '<keyword>'. Exiting..."
   fi
       echo "Found container: $container_name"
```

It should output something like this:

```
Found container: cc-openroaming-provisioning-web-mysql-1
```

2. **Check the mysql container exist & if he is running**, make sure he exists by typing the following command:

```shell
    status=$(docker inspect -f '{{.State.Status}}' $container_name)
    if [ "$status" != "running" ]; then
    echo "Error: Container '$container_name' is not running. Please start the container first."
    fi
    echo "Container '$container_name' is running."
```

3. **Confirm that the CRON package is installed**. Use the following command to
check if `cron` is already in the system, if not, it for installed it for you:

```shell
    if ! dpkg -l | grep -q cron; then
    echo "Cron is not installed. Installing..."
    sudo apt update && apt install -y cron
    else
    echo "Cron is already installed."
    fi
```

---

## Step 2: Open the Crontab File in the root folder

Once `cron` is installed, you need to set up specific jobs to execute the required Symfony console commands. Make sure
you are on the root folder of your project to execute the following steps.

1. **Open Crontab**:
   Run the following command to open the cron scheduler editor (crontab):

   ```bash
   crontab -e
   ```

2. **Add the Cron Job**:
   Add the following lines to the crontab file to schedule your Symfony commands (adjust `<project_folder>` to the
   actual location of the project in your system)
   ```bash
    0 0 * * * /usr/bin/php $HOME/<project_folder>/bin/console clear:deleteUnconfirmedUsers >> $HOME/<project_folder>/var/log/clear_unconfirmed_users.log 2>&1
    30 0 * * * /usr/bin/php $HOME/<project_folder>/bin/console notify:usersWhenProfileExpires >> $HOME/<project_folder>/var/log/notify_users.log 2>&1
   ```

- Replace `<project_folder>` with the path to your project folder (e.g., `/var/www/openroaming-provisioning-web`)

3. **Save and Exit**:
   Save the file and exit the editor. Confirm the cron jobs are set by running:
   ```bash
   crontab -l
   ```

### Explanation of the Cron Job:

- **`0 0 * * *`**: Schedules the command to run **daily at midnight**.
- **`/usr/bin/php`**: Specifies the PHP binary to execute the Symfony console commands (use `which php` to verify the
  exact path to PHP in your environment).
- **`php bin/console`**: Executes the Symfony console commands defined in the project.
- **`clear:deleteUnconfirmedUsers`**: A Symfony command to delete unconfirmed users from the system.
- **`notify:usersWhenProfileExpires`**: A Symfony command to notify users when their profile is about to expire.
- **`>> $HOME/<project_folder>/var/log/clear_unconfirmed_users.log`** or *
  *`>> $HOME/<project_folder>/var/log/notify_users.log`**:
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
