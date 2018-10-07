#!/usr/bin/env bash

docker-compose -f docker-compose.setup.yml up -d
sleep 20
docker-compose exec wordpress wp --allow-root plugin activate ECRedPress
docker-compose stop
docker-compose up
