#!/usr/bin/env bash
set -euo pipefail

# Smoke test for the live API.
# Default mode is read-only. Write actions require --write and explicit IDs.
#
# Example (read-only):
#   ADMIN_USER=admin ADMIN_PASS=admin12345 \
#   ./deploy-tools/smoke_api.sh --base-url "http://127.0.0.1/api"
#
# Example (write checks):
#   ADMIN_USER=admin ADMIN_PASS=admin12345 \
#   DELETE_DELIVERY_RECORD_ID=5 \
#   PAYROLL_RIDER_ID=1 PAYROLL_MONTH=2026-04 PAYROLL_CUTOFF=FIRST \
#   RELEASE_PAYROLL_ID=12 \
#   ./deploy-tools/smoke_api.sh --base-url "http://127.0.0.1/api" --write

BASE_URL="http://127.0.0.1/api"
WRITE_MODE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base-url)
      BASE_URL="${2:-}"
      shift 2
      ;;
    --write)
      WRITE_MODE=1
      shift
      ;;
    *)
      echo "Unknown argument: $1"
      exit 1
      ;;
  esac
done

if [[ -z "${ADMIN_USER:-}" || -z "${ADMIN_PASS:-}" ]]; then
  echo "Missing ADMIN_USER or ADMIN_PASS environment variable."
  exit 1
fi

req() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local token="${4:-}"
  local payload="${5:-}"
  local url="${BASE_URL%/}/${path#/}"
  local tmp
  tmp="$(mktemp)"

  if [[ -n "$payload" ]]; then
    if [[ -n "$token" ]]; then
      code="$(curl -sS -o "$tmp" -w "%{http_code}" -X "$method" "$url" \
        -H "Authorization: Bearer $token" \
        -H "Content-Type: application/json" \
        -d "$payload")"
    else
      code="$(curl -sS -o "$tmp" -w "%{http_code}" -X "$method" "$url" \
        -H "Content-Type: application/json" \
        -d "$payload")"
    fi
  else
    if [[ -n "$token" ]]; then
      code="$(curl -sS -o "$tmp" -w "%{http_code}" -X "$method" "$url" \
        -H "Authorization: Bearer $token")"
    else
      code="$(curl -sS -o "$tmp" -w "%{http_code}" -X "$method" "$url")"
    fi
  fi

  if [[ "$code" != "$expected" ]]; then
    echo "FAIL $method $path expected $expected got $code"
    echo "Response:"
    cat "$tmp"
    rm -f "$tmp"
    exit 1
  fi

  echo "OK   $method $path ($code)"
  cat "$tmp"
  rm -f "$tmp"
}

extract_json_field() {
  local field="$1"
  php -r '
    $json = json_decode(stream_get_contents(STDIN), true);
    $path = explode(".", $argv[1]);
    $value = $json;
    foreach ($path as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
        echo "";
        exit(0);
      }
      $value = $value[$segment];
    }
    if (is_scalar($value)) {
      echo (string) $value;
    }
  ' "$field"
}

echo "=== CORS preflight check ==="
preflight_headers="$(curl -sS -D - -o /dev/null -X OPTIONS "${BASE_URL%/}/login" \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: POST")"
echo "$preflight_headers" | grep -qi "Access-Control-Allow-Origin" || {
  echo "FAIL OPTIONS /login missing Access-Control-Allow-Origin"
  exit 1
}
echo "OK   OPTIONS /login contains Access-Control-Allow-Origin"

echo "=== Admin auth ==="
admin_login_json="$(req POST "login" "200" "" "{\"username\":\"${ADMIN_USER}\",\"password\":\"${ADMIN_PASS}\",\"device_name\":\"smoke-admin\"}")"
admin_token="$(printf "%s" "$admin_login_json" | extract_json_field "data.token")"
if [[ -z "$admin_token" ]]; then
  echo "FAIL could not extract admin token from /login"
  exit 1
fi
echo "OK   extracted admin token"

echo "=== Admin read-only routes ==="
req GET "admin/pending-submissions" "200" "$admin_token" > /dev/null
req GET "admin/pending-remittances" "200" "$admin_token" > /dev/null
req GET "admin/shortages" "200" "$admin_token" > /dev/null
req GET "admin/riders" "200" "$admin_token" > /dev/null
req GET "admin/payrolls" "200" "$admin_token" > /dev/null

if [[ "$WRITE_MODE" -eq 1 ]]; then
  echo "=== Write checks ==="

  if [[ -n "${DELETE_DELIVERY_RECORD_ID:-}" ]]; then
    req POST "admin/remittances/${DELETE_DELIVERY_RECORD_ID}/delete" "200" "$admin_token" "{}" > /dev/null
  else
    echo "SKIP delete pending remittance (set DELETE_DELIVERY_RECORD_ID)"
  fi

  if [[ -n "${PAYROLL_RIDER_ID:-}" && -n "${PAYROLL_MONTH:-}" && -n "${PAYROLL_CUTOFF:-}" ]]; then
    req POST "admin/payrolls/generate" "200" "$admin_token" \
      "{\"rider_id\":${PAYROLL_RIDER_ID},\"payroll_month\":\"${PAYROLL_MONTH}\",\"cutoff_period\":\"${PAYROLL_CUTOFF}\"}" > /dev/null
  else
    echo "SKIP generate payroll (set PAYROLL_RIDER_ID, PAYROLL_MONTH, PAYROLL_CUTOFF)"
  fi

  if [[ -n "${RELEASE_PAYROLL_ID:-}" ]]; then
    req POST "admin/payrolls/${RELEASE_PAYROLL_ID}/release" "200" "$admin_token" \
      '{"payout_method":"CASH","payout_reference":"smoke-test"}' > /dev/null
  else
    echo "SKIP release payroll (set RELEASE_PAYROLL_ID)"
  fi
fi

if [[ -n "${RIDER_USER:-}" && -n "${RIDER_PASS:-}" ]]; then
  echo "=== Rider checks ==="
  rider_login_json="$(req POST "login" "200" "" "{\"username\":\"${RIDER_USER}\",\"password\":\"${RIDER_PASS}\",\"device_name\":\"smoke-rider\"}")"
  rider_token="$(printf "%s" "$rider_login_json" | extract_json_field "data.token")"
  if [[ -z "$rider_token" ]]; then
    echo "FAIL could not extract rider token from /login"
    exit 1
  fi

  req GET "rider/profile" "200" "$rider_token" > /dev/null
  req GET "rider/dashboard" "200" "$rider_token" > /dev/null
  req GET "rider/payrolls" "200" "$rider_token" > /dev/null
  req GET "rider/submissions" "200" "$rider_token" > /dev/null
fi

echo "=== Smoke test passed ==="
