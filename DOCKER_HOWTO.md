# Howto Setup PHP 8.2 CLI Docker Image with PHPStorm

Make sure you have docker installed on your machine. Then run the following command from the
terminal to build the image:

```sh
docker build -t php82-cli .
```

To use this Docker image in PHPStorm, follow these steps:

1. Go to Settings -> PHP -> CLI Interpreter and click on the ... button to open the CLI Interpreters dialog.
2. Click on the + button to add a new interpreter and select From Docker, Vagrant, VM, Remote....
3. In the next dialog, choose Docker, and under Image name, select php82-cli:latest.
4. Click OK to save the new interpreter.
5. Now, select php82-cli:latest and click OK again to apply the settings.

Under Settings -> PHP, you’ll now see that the CLI Interpreter is set to php82-cli:latest. PHPStorm will automatically configure Path mappings: and Docker container: settings. However, the Docker container path will default to `/opt/project` inside the container instead of the `/app` WORKING_DIR defined in the Dockerfile.

To fix this:

1. Click the ... button next to Docker container: under Volume bindings.
2. Change the Container path mapping from `/opt/project` to `/app`.
3. Click OK to save the changes.

Even though a server isn’t running, we need to configure the server settings so PHPStorm recognizes the path mappings between the local project and the Docker container. Follow these steps:

1. Go to Settings -> PHP -> Servers and click the + button to add a new server.
2. Set the server name to php-debug and the host to localhost. It’s important to name the server php-debug because this matches the name set in the Dockerfile. Although localhost isn’t actually used, it’s required to fill in something here.
3. Check Use path mappings and map the local project root to `/app` inside the container.
4. Click OK to save the server configuration.

Your PHPStorm is now set up to use the PHP 8.2 CLI Docker image with the correct path mappings.
