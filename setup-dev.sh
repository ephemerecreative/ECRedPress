#!/usr/bin/env bash

docker-compose -f docker-compose.setup.yml up -d
sleep 20
docker-compose exec wordpress wp --allow-root core install --title="ECRedPress" --url="http://localhost:8000" --admin_user="admin" --admin_password="admin" --admin_email="admin@example.com" --skip-email
docker-compose exec wordpress wp --allow-root plugin activate ECRedPress
docker-compose exec wordpress wp --allow-root rewrite structure '/%year%/%monthnum%/%day%/%postname%/'
docker-compose stop
docker-compose up
