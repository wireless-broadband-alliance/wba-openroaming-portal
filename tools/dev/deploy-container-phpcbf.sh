#!/bin/bash

composer global require "squizlabs/php_codesniffer"
echo 'export PATH="$HOME/.config/composer/vendor/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc
#phpcbf --standard=PSR12 src/
