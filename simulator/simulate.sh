#!/usr/bin/env bash
set -euo pipefail

BROKER_HOST="${SIM_BROKER_HOST:-broker.hivemq.com}"
BROKER_PORT="${SIM_BROKER_PORT:-1883}"
DEVICE_ID="${SIM_DEVICE_ID:-14}"
TOPIC="${SIM_TOPIC:-incubator/${DEVICE_ID}/data}"
INTERVAL="${SIM_INTERVAL_SEC:-5}"

# Ranges are stored in hundredths: 3700 = 37.00, 3770 = 37.70, etc.
TEMP_MIN=3700
TEMP_MAX=3770
HUM_MIN=6000
HUM_MAX=6200

if ! command -v mosquitto_pub >/dev/null 2>&1; then
  echo "[Simulator] mosquitto_pub not found. Install mosquitto-clients first." >&2
  exit 1
fi

trap 'echo; echo "[Simulator] Stopped."; exit 0' INT TERM

echo "[Simulator] Broker  : ${BROKER_HOST}:${BROKER_PORT}"
echo "[Simulator] Topic   : ${TOPIC}"
echo "[Simulator] Interval: ${INTERVAL}s"
echo "[Simulator] Temp    : 37.00-37.70 C"
echo "[Simulator] Humidity: 60.00-62.00 %"

generate() {
  while true; do
    temp_int=$((TEMP_MIN + RANDOM % (TEMP_MAX - TEMP_MIN + 1)))
    hum_int=$((HUM_MIN + RANDOM % (HUM_MAX - HUM_MIN + 1)))

    printf -v temperature '%d.%02d' $((temp_int / 100)) $((temp_int % 100))
    printf -v humidity '%d.%02d' $((hum_int / 100)) $((hum_int % 100))

    payload="{\"temperature\":${temperature},\"humidity\":${humidity}}"

    echo "[Simulator] ${TOPIC} -> ${payload}" >&2
    printf '%s\n' "$payload"

    sleep "$INTERVAL"
  done
}

generate | mosquitto_pub -h "$BROKER_HOST" -p "$BROKER_PORT" -t "$TOPIC" -l
