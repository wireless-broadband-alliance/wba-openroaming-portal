#!/bin/bash
# Script to auto run code scanning tools in fixing mode
vendor/squizlabs/php_codesniffer/bin/phpcbf -s -p --colors --extensions=php --standard=PSR12 src/
vendor/bin/rector process src
