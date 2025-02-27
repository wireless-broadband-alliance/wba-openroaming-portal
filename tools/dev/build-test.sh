#!/bin/bash

docker build -t registry.tetrapi.pt/tcs/cc/cc-openroaming-provisioning-web:tests -f DockerfileTests .
docker push registry.tetrapi.pt/tcs/cc/cc-openroaming-provisioning-web:tests
