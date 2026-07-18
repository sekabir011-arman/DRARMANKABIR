with open('lib/storageAdapter.ts', 'r') as f:
    lines = f.readlines()

# Check what's currently in line 179
print(f"Current line 179 bytes: {lines[178].encode('utf-8').hex()}")
print(f"Current line 193 bytes: {lines[192].encode('utf-8').hex()}")

# Write the correct JavaScript: for (let i = ; i < len; i++)
# The digits  between = and ;
lines[178] = '    for (let i = ; i < len; i++) {\n'

# Write the correct JavaScript: if (keysToRemove.length > )
# The digit  after >
lines[192] = '    if (keysToRemove.length > ) {\n'

with open('lib/storageAdapter.ts', 'w') as f:
    f.writelines(lines)

# Verify
with open('lib/storageAdapter.ts', 'r') as f:
    for i, line in enumerate(f, 1):
        if i in (179, 193):
            print(f"New line {i}: {repr(line)}")
            print(f"Bytes: {line.encode('utf-8').hex()}")
