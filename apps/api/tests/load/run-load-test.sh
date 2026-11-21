#!/bin/bash
STATS_FILE="/tmp/sistlabum-container-stats.log"
rm -f "$STATS_FILE"

echo "=== MEMULAI LOAD TEST KONKURENSI SUNGGUHAN (BATCH 5C - G) ==="
echo "Mulai background sampler tiap 5 detik..."

# Jalankan sampler di background
(
  while true; do
    echo "--- SAMPLE: $(date '+%Y-%m-%d %H:%M:%S') ---" >> "$STATS_FILE"
    docker stats --no-stream --format "{{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}" >> "$STATS_FILE"
    sleep 5
  done
) &
SAMPLER_PID=$!

echo "Sampler running with PID $SAMPLER_PID"

# Jalankan k6
cd "$(dirname "$0")"
k6 run batch5c-realistic-concurrency.js
K6_STATUS=$?

# Hentikan sampler
kill $SAMPLER_PID 2>/dev/null
wait $SAMPLER_PID 2>/dev/null

echo "=== SAMPLING SELESAI ==="
exit $K6_STATUS
