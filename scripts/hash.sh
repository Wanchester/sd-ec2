#!/usr/bin/env bash
git config --global --add safe.directory "$1"
cd "$1"
git rev-parse HEAD