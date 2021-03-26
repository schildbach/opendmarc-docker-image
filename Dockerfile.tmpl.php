<?php
$var = getopt('', ['version:', 'dockerfile:']);
$isAlpineImage = $var['dockerfile'] === 'alpine';
?>
# AUTOMATICALLY GENERATED
# DO NOT EDIT THIS FILE DIRECTLY, USE /Dockerfile.tmpl.php

<? if ($isAlpineImage) { ?>
# https://hub.docker.com/_/alpine
FROM alpine:3.13.3
<? } else { ?>
# https://hub.docker.com/_/debian
FROM debian:buster-slim
<? } ?>

ARG opendmarc_ver=<?= explode('-', $var['version'])[0]."\n"; ?>
ARG opendmarc_sum=<?= "6045fb7d2be8f0ffdeca07324857d92908a41c6792749017c2fcc1058f05f55317b1919c67c780827dd7094ec8fff2e1fa4aeb5bab7ff7461537957af2652748\n"; ?>
ARG s6_overlay_ver=2.2.0.3

LABEL org.opencontainers.image.source="\
    https://github.com/instrumentisto/opendmarc-docker-image"


# Build and install OpenDMARC
<? if ($isAlpineImage) { ?>
RUN apk update \
 && apk upgrade \
 && apk add --no-cache \
        ca-certificates \
<? } else { ?>
RUN apt-get update \
 && apt-get upgrade -y \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            inetutils-syslogd \
            ca-certificates \
<? } ?>
 && update-ca-certificates \
    \
 # Install OpenDMARC dependencies
<? if ($isAlpineImage) { ?>
 && apk add --no-cache \
        libmilter \
<? } else { ?>
 && apt-get install -y --no-install-recommends --no-install-suggests \
            libmilter1.0.1 \
<? } ?>
    \
 # Install tools for building
<? if ($isAlpineImage) { ?>
 && apk add --no-cache --virtual .tool-deps \
        curl coreutils autoconf g++ libtool make \
<? } else { ?>
 && toolDeps=" \
        curl make gcc g++ libc-dev \
    " \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            $toolDeps \
<? } ?>
    \
 # Install OpenDMARC build dependencies
<? if ($isAlpineImage) { ?>
 && apk add --no-cache --virtual .build-deps \
        libmilter-dev \
<? } else { ?>
 && buildDeps=" \
        libmilter-dev \
    " \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            $buildDeps \
<? } ?>
    \
 # Download and prepare OpenDMARC sources
 && curl -fL -o /tmp/opendmarc.tar.gz \
         https://downloads.sourceforge.net/project/opendmarc/opendmarc-${opendmarc_ver}.tar.gz \
 && (echo "${opendmarc_sum}  /tmp/opendmarc.tar.gz" \
         | sha512sum -c -) \
 && tar -xzf /tmp/opendmarc.tar.gz -C /tmp/ \
 && cd /tmp/opendmarc-* \
<? if ($isAlpineImage) { ?>
 # Patch NETDB_* musl libc problem.
 # Details: https://github.com/instrumentisto/docker-mailserver/issues/4
 && sed -i '1s;^;#if !defined(NETDB_INTERNAL)\n#  define NETDB_INTERNAL (-1)\n#endif\n#if !defined(NETDB_SUCCESS)\n#  define NETDB_SUCCESS (0)\n#endif\n\n;' \
        libopendmarc/opendmarc_internal.h \
<? } ?>
    \
 # Build OpenDMARC from sources
 && ./configure \
        --prefix=/usr \
        --sysconfdir=/etc/opendmarc \
        # No documentation included to keep image size smaller
        --docdir=/tmp/opendmarc/doc \
        --infodir=/tmp/opendmarc/info \
        --mandir=/tmp/opendmarc/man \
 && make \
    \
 # Create OpenDMARC user and group
<? if ($isAlpineImage) { ?>
 && addgroup -S -g 91 opendmarc \
 && adduser -S -u 90 -D -s /sbin/nologin \
            -H -h /run/opendmarc \
            -G opendmarc -g opendmarc \
            opendmarc \
 && addgroup opendmarc mail \
<? } else { ?>
 && addgroup --system --gid 91 opendmarc \
 && adduser --system --uid 90 --disabled-password --shell /sbin/nologin \
            --no-create-home --home /run/opendmarc \
            --ingroup opendmarc --gecos opendmarc \
            opendmarc \
 && adduser opendmarc mail \
<? } ?>
    \
 # Install OpenDMARC
 && make install \
 # Prepare run directory
 && install -d -o opendmarc -g opendmarc /run/opendmarc/ \
 # Preserve licenses
 && install -d /usr/share/licenses/opendmarc/ \
 && mv /tmp/opendmarc/doc/LICENSE* \
       /usr/share/licenses/opendmarc/ \
 # Prepare configuration directories
 && install -d /etc/opendmarc/conf.d/ \
    \
 # Cleanup unnecessary stuff
<? if ($isAlpineImage) { ?>
 && apk del .tool-deps .build-deps \
 && rm -rf /var/cache/apk/* \
<? } else { ?>
 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
            $toolDeps $buildDeps \
 && rm -rf /var/lib/apt/lists/* \
           /etc/*/inetutils-syslogd \
<? } ?>
           /tmp/*


# Install s6-overlay
<? if ($isAlpineImage) { ?>
RUN apk add --update --no-cache --virtual .tool-deps \
        curl \
<? } else { ?>
RUN apt-get update \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            curl \
<? } ?>
 && curl -fL -o /tmp/s6-overlay.tar.gz \
         https://github.com/just-containers/s6-overlay/releases/download/v${s6_overlay_ver}/s6-overlay-amd64.tar.gz \
<? if ($isAlpineImage) { ?>
 && tar -xzf /tmp/s6-overlay.tar.gz -C / \
<? } else { ?>
 # In Debian stretch: /bin -> /usr/bin
 # So unpacking s6-overlay.tar.gz to the / will replace /bin symlink with
 # /bin directory from archive.
 # To avoid this we need to copy content of /bin manually.
 && mkdir -p /tmp/s6-overlay \
 && tar -xzf /tmp/s6-overlay.tar.gz -C /tmp/s6-overlay/ \
 && cp -rf /tmp/s6-overlay/bin/* /bin/ \
 && rm -rf /tmp/s6-overlay/bin \
           /tmp/s6-overlay/usr/bin/execlineb \
 && cp -rf /tmp/s6-overlay/* / \
<? } ?>
    \
 # Cleanup unnecessary stuff
<? if ($isAlpineImage) { ?>
 && apk del .tool-deps \
 && rm -rf /var/cache/apk/* \
<? } else { ?>
 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
            curl \
 && rm -rf /var/lib/apt/lists/* \
<? } ?>
           /tmp/*

ENV S6_BEHAVIOUR_IF_STAGE2_FAILS=2 \
    S6_CMD_WAIT_FOR_SERVICES=1


COPY rootfs /

RUN chmod +x /etc/services.d/*/run


EXPOSE 8893

ENTRYPOINT ["/init"]

CMD ["opendmarc", "-f"]
