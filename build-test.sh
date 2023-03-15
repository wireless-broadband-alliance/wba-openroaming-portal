#!/bin/bash

docker build -t registry.tetrapi.pt/tcs/wba/wba-provisioning-web:tests -f DockerfileTests .
docker push registry.tetrapi.pt/tcs/wba/wba-provisioning-web:tests
