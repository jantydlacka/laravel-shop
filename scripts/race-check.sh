#!/usr/bin/env bash
# Runs the stock race check inside the Sail container. Optional argument: number of processes.
set -euo pipefail
cd "$(dirname "$0")/.."
exec ./vendor/bin/sail composer race -- "$@"
