FROM python:3.14-slim-bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends php-cli php-zip php-xml && rm -rf /var/lib/apt/lists/*

WORKDIR /repo

COPY requirements-ci.txt /tmp/requirements-ci.txt

RUN python -m pip install --no-cache-dir -r /tmp/requirements-ci.txt && python -m playwright install --with-deps chromium
