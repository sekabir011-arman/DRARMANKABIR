with open('lib/storageAdapter.ts', 'r') as f:
    lines = f.readlines()

# Fix line 179 (index 178): for (let i = <zero>; i < len; i++)
lines[178] = '    for (let i = ; i < len; i++) {\n'

# Fix line 193 (index 192): if (keysToRemove.length > )
lines[192] = '    if (keysToRemove.length > ) {\n'

with open('lib/storageAdapter.ts', 'w') as f:
    f.writelines(lines)

print("Fixed!")
with open('lib/storageAdapter.ts', 'r') as f:
    for i, line in enumerate(f, 1):
        if i in (179, 193):
            print(f"Line {i}: {repr(line)}")
