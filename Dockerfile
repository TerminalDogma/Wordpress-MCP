# syntax=docker/dockerfile:1
FROM wordpress:cli-php8.3@sha256:6bcc7bb91f5157c4b915e74ee131873e99c1c1ff48f602e4a6f6cd17827305cb AS cli
FROM wordpress:php8.3-apache@sha256:848b03bb7b4b160e3a670454e8e928e309c9aaeb1c2584e917f42ab5197662e2

COPY --from=cli /usr/local/bin/wp /usr/local/bin/wp

# ACF Free, from its official upstream repository, with an immutable revision
# and archive checksum. The optional secret supports managed proxy CA trust.
RUN --mount=type=secret,id=proxy_ca \
    set -eu; \
    if [ -f /run/secrets/proxy_ca ]; then export CURL_CA_BUNDLE=/run/secrets/proxy_ca; fi; \
    curl --fail --location --retry 3 \
      https://github.com/AdvancedCustomFields/acf/archive/fa4ceb7b2773ac520d9b3c36d7271853bccd7a8f.tar.gz \
      --output /tmp/acf.tar.gz; \
    echo 'caf1ef3f5abaf8b4e78f5c230bda43d20b66b8316d5f6b8d46d93d5dd3ac3c57  /tmp/acf.tar.gz' | sha256sum --check --strict; \
    mkdir -p /opt/acf; \
    tar -xzf /tmp/acf.tar.gz -C /opt/acf --strip-components=1; \
    rm -f /tmp/acf.tar.gz; \
    rm -rf /opt/acf/.github /opt/acf/.gitignore

COPY --chmod=0644 docker/setup.sh /opt/wordpress/setup.sh
COPY --chmod=0644 docker/verify.php /opt/wordpress/verify.php
RUN chmod 0755 /opt/wordpress
