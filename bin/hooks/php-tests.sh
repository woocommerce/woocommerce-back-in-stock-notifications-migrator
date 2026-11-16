#!/usr/bin/env bash
set -euo pipefail

# Use the same WooCommerce release as the native leg, in a fresh PHP process.
composer run test:php:mappers
