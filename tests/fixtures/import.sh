#!/usr/bin/env bash

# Prepare variables
_CURDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
_CURL_CMD="curl -si --silent -H \"X-Storage-User: $TEST_SW_USERNAME\" -H \"X-Storage-Pass: $TEST_SW_APIKEY\" $TEST_SW_URL"
_AUTH_MASK="$_CURL_CMD | grep '%s' | awk '{print \$2}' | tr -d '[[:space:]]'"
_STORAGE_URL=$(eval $(printf "$_AUTH_MASK" "X-Storage-Url"))
_STORAGE_TOKEN=$(eval $(printf "$_AUTH_MASK" "X-Storage-Token"))

# Fill OpenStack storage with fixtures
for d in $(find $_CURDIR -mindepth 1 -type d)
do
    container="$_STORAGE_URL/$(basename "${d}")"

    curl --silent -X PUT -H "X-Auth-Token: $_STORAGE_TOKEN" $container 1>/dev/null

    for f in $(find $d -type f)
    do
        curl --silent -X PUT -H "X-Auth-Token: $_STORAGE_TOKEN" -T $f "$container/$(basename "${f}")"
    done
done