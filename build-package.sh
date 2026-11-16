#!/bin/sh
# Builds gree.tgz for "Маркет дополнений -> Добавить/обновить вручную".
# The archive holds one folder with the MajorDoMo tree (modules, templates, img,
# scripts); the market unpacks it and copies the folder contents into the root.
set -e
cd "$(dirname "$0")"
OUT="${1:-dist}"
TMP=$(mktemp -d)
mkdir -p "$TMP/gree" "$OUT"
cp -R modules templates img scripts "$TMP/gree/"
find "$TMP/gree" -name '.DS_Store' -delete
tar --owner=0 --group=0 -czf "$OUT/gree.tgz" -C "$TMP" gree
rm -rf "$TMP"
echo "$OUT/gree.tgz"
