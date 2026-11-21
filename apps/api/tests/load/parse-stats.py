#!/usr/bin/env python3
import sys
import re

stats_file = "/tmp/sistlabum-container-stats.log"

containers = {}

try:
    with open(stats_file, "r") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("---") or line.startswith("Starting"):
                continue
            parts = line.split("\t")
            if len(parts) >= 3:
                name = parts[0].strip()
                cpu_str = parts[1].replace("%", "").strip()
                mem_str = parts[2].strip()
                try:
                    cpu_val = float(cpu_str)
                except ValueError:
                    cpu_val = 0.0

                # Extract numeric MB for memory
                mem_match = re.search(r"([\d\.]+)\s*(MiB|GiB|kiB)", mem_str)
                mem_mb = 0.0
                if mem_match:
                    val = float(mem_match.group(1))
                    unit = mem_match.group(2)
                    if unit == "GiB":
                        mem_mb = val * 1024
                    elif unit == "MiB":
                        mem_mb = val
                    elif unit == "kiB":
                        mem_mb = val / 1024

                if name not in containers:
                    containers[name] = {"peak_cpu": 0.0, "peak_mem_mb": 0.0, "raw_mem": mem_str}

                if cpu_val > containers[name]["peak_cpu"]:
                    containers[name]["peak_cpu"] = cpu_val
                if mem_mb > containers[name]["peak_mem_mb"]:
                    containers[name]["peak_mem_mb"] = mem_mb
                    containers[name]["raw_mem"] = mem_str

    print("=== PEAK RESOURCE CONSUMPTION DURING LOAD TEST ===")
    print(f"{'Container':<22} | {'Peak CPU (%)':<14} | {'Peak RAM (MB / Usage)':<25}")
    print("-" * 68)
    for name, data in sorted(containers.items()):
        print(f"{name:<22} | {data['peak_cpu']:>10.2f} %  | {data['peak_mem_mb']:>8.1f} MB ({data['raw_mem']})")

except Exception as e:
    print(f"Error parsing stats: {e}")
