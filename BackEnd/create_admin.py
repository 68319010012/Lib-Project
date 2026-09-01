
"""
Create an admin account. Run this directly on the server/machine that hosts
the database — there is no HTTP endpoint for this, on purpose: admin accounts
must never be creatable over the network.

Usage:
    python create_admin.py <username>
Then type the password when prompted (input is hidden, never echoed or logged).
"""

import getpass
import re
import sys

import bcrypt

from db import get_db_connection

MIN_PASSWORD_LENGTH = 8


def create_admin(username, password):
    conn = get_db_connection()
    cur = conn.cursor()
    try:
        cur.execute("SELECT user_id FROM users WHERE username = %s", (username,))
        if cur.fetchone() is not None:
            print(f"Error: username '{username}' already exists.")
            sys.exit(1)

        password_hash = bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")
        cur.execute(
            """
            INSERT INTO users (username, password_hash, role, student_id, account_status)
            VALUES (%s, %s, 'admin', NULL, 'approved')
            """,
            (username, password_hash),
        )
        conn.commit()
        print(f"Admin account '{username}' created.")
    finally:
        cur.close()
        conn.close()


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python create_admin.py <username>")
        sys.exit(1)

    admin_username = sys.argv[1].strip()
    if not re.fullmatch(r"[A-Za-z0-9_.-]{3,50}", admin_username):
        print("Error: username must be 3-50 chars, letters/numbers/._- only.")
        sys.exit(1)

    admin_password = getpass.getpass("New admin password: ")
    confirm_password = getpass.getpass("Confirm password: ")

    if admin_password != confirm_password:
        print("Error: passwords do not match.")
        sys.exit(1)
    if len(admin_password) < MIN_PASSWORD_LENGTH:
        print(f"Error: password must be at least {MIN_PASSWORD_LENGTH} characters.")
        sys.exit(1)

    create_admin(admin_username, admin_password)
