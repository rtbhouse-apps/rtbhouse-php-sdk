#!/usr/bin/env bash

set -e
set -o pipefail

cd "`dirname $0`"
cd ..

./vendor/bin/phpunit tests
