#!/usr/bin/env python3
import json
import os

print("Writing full generator script...")

# We will read scripts/mock_data.json and build the final self-contained index.html
with open('scripts/mock_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Let's inspect the data
print("Data loaded successfully.")
