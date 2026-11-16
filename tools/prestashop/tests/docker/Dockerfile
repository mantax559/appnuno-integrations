ARG PRESTASHOP_BASE_IMAGE

FROM alpine:3.21 AS prestashop-source

ARG PRESTASHOP_SOURCE_REPOSITORY
ARG PRESTASHOP_SOURCE_TAG

RUN apk add --no-cache ca-certificates git \
    && git clone --depth 1 --branch "$PRESTASHOP_SOURCE_TAG" "https://github.com/$PRESTASHOP_SOURCE_REPOSITORY.git" /tmp/prestashop-source \
    && test "$(git -C /tmp/prestashop-source describe --tags --exact-match HEAD)" = "$PRESTASHOP_SOURCE_TAG"

FROM ${PRESTASHOP_BASE_IMAGE}

COPY --from=prestashop-source /tmp/prestashop-source /tmp/prestashop-source

RUN find /var/www/html -mindepth 1 -maxdepth 1 -exec rm -rf {} + \
    && cp -a /tmp/prestashop-source/. /var/www/html/ \
    && rm -rf /var/www/html/.git \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /tmp/prestashop-source
