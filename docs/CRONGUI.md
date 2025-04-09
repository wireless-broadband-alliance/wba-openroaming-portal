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

## Step 1: Test the portal automation Commands Manually

Before relying on cron to execute the commands, manually test if portal execution works as expected:

```bash
  php bin/console clear:deleteUnconfirmedUsers
```

```bash
  php bin/console notify:usersWhenProfileExpires
```

## Step 2: Identify the Container and Install Cron

1. **Confirm that the CRON package is installed**. Use the following command to
   check if `cron` is already in the system, if not, it for installed it for you:

```shell
  dpkg -l | grep cron || (echo "Cron is not installed. Installing..." && sudo apt update && sudo apt install -y cron && echo "Cron has been installed.")
```

2. **Identify the Docker Container**:
   Run the following command to automatically detect if the correct `<container-web>` is running:

```shell
  docker ps | grep web | grep -i web-1 | awk '{print $1}'
```

or

```shell
  docker ps --filter "name=web-1" --format "{{.ID}}"
```

Both of these commands will return the container ID (a hash) of the running `<container-web>`. If no output is returned,
the container is not running, and you need to start them to verify the existence of this container.

---

## Step 3: Open the Crontab File in the root folder

Once `cron` is installed, you need to set up specific jobs to execute the required Symfony automation commands. Make sure
you are on the root folder of the project to execute the following steps:

1. **Open Crontab**:
   Run the following command to open the cron scheduler editor (crontab):

   ```bash
   crontab -e
   ```

2. **Add the Cron Job**:
   Add the following lines to the crontab file to schedule the Symfony automation commands.

   ```bash
   0 0 * * * docker exec -it $(docker ps | grep web | grep -i web-1 | awk '{print $1}') php bin/console clear:deleteUnconfirmedUsers
   30 0 * * * docker exec -it $(docker ps | grep web | grep -i web-1 | awk '{print $1}') php bin/console notify:usersWhenProfileExpires
   ``` 

**Please make sure you type this commands on the end of the file.**

- The first command: Runs every day at **midnight (00:00)** to clear unconfirmed users.
- The second command: Runs every day at **12:30 AM (00:30)** to notify users about profile expirations.

3. **Save and Exit**:
   Save the file and exit the editor.

- In **nano**, press `CTRL + O`, then `ENTER` to save, and `CTRL + X` to exit.

To confirm the cron jobs are set please run this another command:

```bash
   crontab -l
```

---

## Step 4: Enable and Start the Cron Service

After setting up the cron job, you need to enable and start the cron service to ensure it runs.

1. **Start the Cron Service**:
   Start the cron service:
   ```bash
   sudo service cron start
   ```

2. **Verify Cron Service Is Running**:
   Optionally, confirm that the cron service is active:
   ```bash
   sudo service cron status
   ```

---

## Step 5: Check Scheduled Cron Jobs

To verify that your cron job is added and scheduled properly, list the active cron jobs:

```bash
  crontab -l
```

You should see the command you added in Step 3.
