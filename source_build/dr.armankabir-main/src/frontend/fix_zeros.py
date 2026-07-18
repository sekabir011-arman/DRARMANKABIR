#!/usr/bin/env python3
import re

with open('lib/storageAdapter.ts', 'r') as f:
    content = f.read()

# Fix for (let i = ; i < len; i++) to have 
content = re.sub(r'for \(let i = ; i < len; i\+\+\)', 'for (let i = ; i < len; i++)', content)

# Fix keysToRemove.length > ) to have 
content = re.sub(r'keysToRemove\.length > \)', 'keysToRemove.length > )', content)

# Fix return ; to have 
content = re.sub(r'return ;', 'return ;', content)

with open('lib/storageAdapter.ts', 'w') as f:
    f.write(content)

print("Fixed!")

# Verify
with open('lib/storageAdapter.ts', 'r') as f:
    for i, line in enumerate(f, 1):
        if i in (157, 179, 193):
            print(f"Line {i}: {repr(line)}")
