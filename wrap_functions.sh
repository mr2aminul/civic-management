#!/bin/bash

# Backup the original file
cp assets/includes/file_manager_helper.php assets/includes/file_manager_helper.php.backup_$(date +%Y%m%d_%H%M%S)

# Create a temporary Python script to do the wrapping
cat > /tmp/wrap_functions.py << 'PYTHON_SCRIPT'
import re
import sys

def wrap_functions(content):
    lines = content.split('\n')
    result = []
    i = 0

    while i < len(lines):
        line = lines[i]

        # Check if this line starts a function (and isn't already wrapped)
        if re.match(r'^function (fm_[a-z_]+)\s*\(', line):
            func_match = re.match(r'^function (fm_[a-z_]+)\s*\(', line)
            if func_match:
                func_name = func_match.group(1)

                # Check if previous line already has function_exists wrapper
                if i > 0 and 'function_exists' in lines[i-1]:
                    # Already wrapped, just add the line
                    result.append(line)
                else:
                    # Need to wrap this function
                    result.append(f"if (!function_exists('{func_name}')) {{")
                    result.append(line)

                    # Now find the closing brace of this function
                    i += 1
                    brace_count = 0
                    found_open = False

                    while i < len(lines):
                        current_line = lines[i]
                        result.append(current_line)

                        # Count braces
                        brace_count += current_line.count('{')
                        if current_line.count('{') > 0:
                            found_open = True
                        brace_count -= current_line.count('}')

                        # If we've found the opening brace and count is back to 0, function is done
                        if found_open and brace_count == 0:
                            result.append('}')
                            break

                        i += 1
        else:
            result.append(line)

        i += 1

    return '\n'.join(result)

# Read the file
with open('assets/includes/file_manager_helper.php', 'r') as f:
    content = f.read()

# Wrap functions
wrapped_content = wrap_functions(content)

# Write back
with open('assets/includes/file_manager_helper.php', 'w') as f:
    f.write(wrapped_content)

print("All functions wrapped successfully!")
PYTHON_SCRIPT

# Run the Python script
python3 /tmp/wrap_functions.py

# Clean up
rm /tmp/wrap_functions.py

echo "Done! Functions wrapped with function_exists checks."
