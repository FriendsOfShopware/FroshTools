ARG PHP_VERSION=8.3
ARG NODE_VERSION=24
ARG WEBSERVER=caddy

FROM ghcr.io/shopware/docker-dev:php${PHP_VERSION}-node${NODE_VERSION}-${WEBSERVER} AS base-image

USER root
RUN apk add graphviz
USER www-data
