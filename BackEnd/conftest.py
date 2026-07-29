import pytest

from app import app as flask_app
from app import limiter
from create_admin import create_admin
from db import get_db_connection


@pytest.fixture
def client():
    flask_app.config["TESTING"] = True
    limiter.enabled = False
    with flask_app.test_client() as test_client:
        yield test_client
    limiter.enabled = True


@pytest.fixture
def unused_student_id():
    conn = get_db_connection()
    cur = conn.cursor()
    try:
        cur.execute(
            """
            SELECT s.student_id
            FROM students s
            LEFT JOIN users u ON u.student_id = s.student_id
            WHERE u.user_id IS NULL
            LIMIT 1
            """
        )
        row = cur.fetchone()
        assert row is not None, "no unused student_id available in the students table"
        return row[0]
    finally:
        cur.close()
        conn.close()


def _delete_user_and_logs(username):
    conn = get_db_connection()
    cur = conn.cursor()
    try:
        cur.execute(
            "DELETE cl FROM checkin_logs cl JOIN users u ON u.user_id = cl.user_id WHERE u.username = %s",
            (username,),
        )
        cur.execute("DELETE FROM users WHERE username = %s", (username,))
        conn.commit()
    finally:
        cur.close()
        conn.close()


@pytest.fixture
def registered_student(client, unused_student_id):
    """Registers a student account (auto-approved, auto-logged-in) and cleans it up afterward."""
    username = f"pytest_student_{unused_student_id}"
    password = "TestPass!123"
    client.post(
        "/register",
        json={"student_id": unused_student_id, "username": username, "password": password},
    )
    yield {"username": username, "password": password, "student_id": unused_student_id}
    _delete_user_and_logs(username)


@pytest.fixture
def temp_admin():
    username = "pytest_admin_temp"
    password = "TestAdminPass!123"
    create_admin(username, password)
    yield {"username": username, "password": password}
    conn = get_db_connection()
    cur = conn.cursor()
    try:
        cur.execute("DELETE FROM users WHERE username = %s", (username,))
        conn.commit()
    finally:
        cur.close()
        conn.close()
