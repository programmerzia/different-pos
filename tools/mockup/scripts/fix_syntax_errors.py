#!/usr/bin/env python3
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PARTS_DIR = os.path.join(BASE_DIR, "scripts", "parts")

# 1. Remove old canAccess from render_shell.py
shell_path = os.path.join(PARTS_DIR, "render_shell.py")
with open(shell_path, "r", encoding="utf-8") as f:
    shell_content = f.read()

old_can_access = """      // Permission filtering helper based on viewAsRole
      const canAccess = (requiredRole) => {
        const hierarchy = { 'Super Admin': 6, 'Owner': 5, 'Manager': 4, 'Accountant': 3, 'Stock Keeper': 2, 'Cashier': 1 };
        return (hierarchy[viewAsRole] || 1) >= (hierarchy[requiredRole] || 1);
      };"""

if old_can_access in shell_content:
    shell_content = shell_content.replace(old_can_access, "      // canAccess is master permission checker from app_state.py")
    with open(shell_path, "w", encoding="utf-8") as f:
        f.write(shell_content)
    print("Removed duplicate canAccess from render_shell.py.")

# 2. Fix \D in render_all_modals.py
modals_path = os.path.join(PARTS_DIR, "render_all_modals.py")
with open(modals_path, "r", encoding="utf-8") as f:
    modals_content = f.read()

modals_content = modals_content.replace("replace(/\\D/g, '')", "replace(/\\\\D/g, '')")
modals_content = modals_content.replace("replace(/\\s/g, '')", "replace(/\\\\s/g, '')")

with open(modals_path, "w", encoding="utf-8") as f:
    f.write(modals_content)
print("Fixed escape sequence in render_all_modals.py.")
