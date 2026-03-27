#!/usr/bin/env bash
set -euo pipefail

# ktp-banner リリースZIP作成スクリプト
# 出力先: /Users/kantanpro/Desktop/ktp_banner_TEST_UP

PLUGIN_SLUG="ktp-banner"
OUTPUT_DIR="/Users/kantanpro/Desktop/ktp_banner_TEST_UP"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="${SCRIPT_DIR}"

if [[ ! -f "${PLUGIN_DIR}/ktp-banner.php" ]]; then
  echo "Error: ${PLUGIN_DIR}/ktp-banner.php が見つかりません。"
  exit 1
fi

mkdir -p "${OUTPUT_DIR}"

VERSION="$(sed -n 's/^ \* Version: \(.*\)$/\1/p' "${PLUGIN_DIR}/ktp-banner.php" | head -n 1 | tr -d '\r')"
if [[ -z "${VERSION}" ]]; then
  VERSION="dev"
fi

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
ZIP_NAME="${PLUGIN_SLUG}-v${VERSION}-${TIMESTAMP}.zip"
ZIP_PATH="${OUTPUT_DIR}/${ZIP_NAME}"

cd "${PLUGIN_DIR}/.."

zip -r "${ZIP_PATH}" "${PLUGIN_SLUG}" \
  -x "${PLUGIN_SLUG}/.git/*" \
  -x "${PLUGIN_SLUG}/.git" \
  -x "${PLUGIN_SLUG}/.DS_Store" \
  -x "${PLUGIN_SLUG}/**/.DS_Store" \
  -x "${PLUGIN_SLUG}/*.zip"

echo "Release ZIP created:"
echo "${ZIP_PATH}"
