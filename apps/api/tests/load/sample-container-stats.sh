#!/bin/sh
OUTPUT_FILE="/tmp/sistlabum-container-stats.log"
rm -f "$OUTPUT_FILE"
echo "Starting container resource sampling every 5s..."

while true; do
  TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")
  echo "--- SAMPLE: $TIMESTAMP ---" >> "$OUTPUT_FILE"
  docker stats --no-stream --format "{{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}" >> "$OUTPUT_FILE"
  sleep 5
done
