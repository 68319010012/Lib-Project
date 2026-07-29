import os

import mysql.connector
from dotenv import load_dotenv

load_dotenv()

DB_CONFIG = dict(
    host=os.environ.get("DB_HOST", "localhost"),
    user=os.environ.get("DB_USER", "root"),
    password=os.environ.get("DB_PASSWORD", ""),
    database=os.environ.get("DB_NAME", "library_checkin"),
)
# NOTE: dev-only defaults (XAMPP). Real deployments should set these via .env
# (never commit .env — see .gitignore) with a real, non-root DB user/password.


def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)
