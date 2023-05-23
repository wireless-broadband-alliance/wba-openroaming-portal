
# OpenRoaming Provisioning Portal

The OpenRoaming Provisioning Portal is a tool that enables automated device authentication on wireless networks. Here's a simple guide to help you get started with it.

## Prerequisites
- Docker
- Docker-compose
- Git (if you plan to clone the repository)

## Getting Started
1. First clone the repository or download the zipped project package.

```bash
git clone <repository-url>
```

OR

Unzip the project package.

2. Authenticate with TETRAPI GitLab registry:

```bash
docker login registry.tetrapi.pt
```

Alternatively, you can build the image yourself using the provided Dockerfile.

3. Update your environment variables. You can find a sample in the `.env.sample` file provided in the project root directory. Make sure you duplicate the sample file and rename it to `.env`.

4. Run docker-compose to build and start the services:

```bash
docker-compose up -d
```

## Post Installation
Once the containers are up and running, you'll need to perform a few more steps.

1. Go into the `web` container:

```bash
docker exec -it <web-container-id> bash
```

2. Run migrations to set up your database schema:

```bash
php bin/console doctrine:migrations:migrate
```

3. Load fixtures to populate your database with initial data:

```bash
php bin/console doctrine:fixtures:load
```

4. Upload your certificate files to the `signing-keys` directory.

5. Inside the `web` container, navigate to the `tools` directory and run the `generatePfx` script:

```bash
cd tools
sh generatePfxSigningKey.sh
```

6. Finally, connect to your MySQL database instance and update the details on the `settings` table according to your requirements.

## Troubleshooting
If you encounter any issues during setup, please check the logs of the relevant Docker container. You can view the logs with the following command:

```bash
docker logs <container-id>
```
