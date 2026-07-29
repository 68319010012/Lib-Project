def test_register_missing_fields(client):
    resp = client.post("/register", json={})
    assert resp.status_code == 400
    assert "error" in resp.get_json()


def test_register_unknown_student_id(client):
    resp = client.post(
        "/register",
        json={
            "student_id": "00000000000",
            "username": "pytest_nonexistent_student",
            "password": "TestPass!123",
        },
    )
    assert resp.status_code == 404
    assert resp.get_json()["error"] == "student_id not found"


def test_register_logs_in_immediately(client, registered_student):
    resp = client.get("/me")
    assert resp.status_code == 200
    assert resp.get_json()["username"] == registered_student["username"]
    assert resp.get_json()["account_status"] == "approved"


def test_register_duplicate_username_rejected(client, registered_student, unused_student_id):
    resp = client.post(
        "/register",
        json={
            "student_id": unused_student_id,
            "username": registered_student["username"],
            "password": "AnotherPass!123",
        },
    )
    assert resp.status_code == 409


def test_register_duplicate_student_rejected(client, registered_student):
    resp = client.post(
        "/register",
        json={
            "student_id": registered_student["student_id"],
            "username": "pytest_another_username",
            "password": "AnotherPass!123",
        },
    )
    assert resp.status_code == 409


def test_login_wrong_password(client, registered_student):
    client.post("/logout")
    resp = client.post(
        "/login",
        json={"username": registered_student["username"], "password": "wrong-password"},
    )
    assert resp.status_code == 401
    assert resp.get_json()["error"] == "invalid username or password"


def test_login_works_right_after_registration(client, registered_student):
    client.post("/logout")
    resp = client.post(
        "/login",
        json={"username": registered_student["username"], "password": registered_student["password"]},
    )
    assert resp.status_code == 200
    assert resp.get_json()["role"] == "student"


def test_checkin_requires_login(client):
    resp = client.post("/checkin")
    assert resp.status_code == 401


def test_full_flow_register_checkin_toggle_and_reports(client, registered_student, temp_admin):
    first = client.post("/checkin")
    assert first.status_code == 200
    assert first.get_json()["type"] == "in"

    second = client.post("/checkin")
    assert second.status_code == 200
    assert second.get_json()["type"] == "out"

    client.post("/logout")
    client.post("/login", json={"username": temp_admin["username"], "password": temp_admin["password"]})

    report = client.get("/admin/reports?academic_year=doesnotmatter")
    assert report.status_code == 200

    report_all = client.get("/admin/reports")
    entries = [
        row
        for row in report_all.get_json()
        if row["student_id"] == registered_student["student_id"]
    ]
    assert len(entries) == 2
    types = {row["type"] for row in entries}
    assert types == {"in", "out"}


def test_me_requires_login(client):
    resp = client.get("/me")
    assert resp.status_code == 401


def test_me_returns_student_profile(client, registered_student):
    resp = client.get("/me")
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["username"] == registered_student["username"]
    assert data["student_id"] == registered_student["student_id"]
    assert data["role"] == "student"


def test_me_history_reflects_checkins(client, registered_student):
    client.post("/checkin")
    client.post("/checkin")
    resp = client.get("/me/history")
    assert resp.status_code == 200
    rows = resp.get_json()
    assert len(rows) == 2
    assert rows[0]["type"] == "out"
    assert rows[1]["type"] == "in"


def test_change_password_wrong_current_password(client, registered_student):
    resp = client.post(
        "/profile/change-password",
        json={"current_password": "wrong-password", "new_password": "NewPass!1234"},
    )
    assert resp.status_code == 401


def test_change_password_too_short(client, registered_student):
    resp = client.post(
        "/profile/change-password",
        json={"current_password": registered_student["password"], "new_password": "short"},
    )
    assert resp.status_code == 400


def test_change_password_success_then_relogin(client, registered_student):
    resp = client.post(
        "/profile/change-password",
        json={"current_password": registered_student["password"], "new_password": "NewPass!1234"},
    )
    assert resp.status_code == 200
    client.post("/logout")

    old_password_resp = client.post(
        "/login",
        json={"username": registered_student["username"], "password": registered_student["password"]},
    )
    assert old_password_resp.status_code == 401

    new_password_resp = client.post(
        "/login",
        json={"username": registered_student["username"], "password": "NewPass!1234"},
    )
    assert new_password_resp.status_code == 200


def test_admin_members_requires_admin_role(client, registered_student):
    resp = client.get("/admin/members")
    assert resp.status_code == 403


def test_admin_members_includes_newly_registered_student(client, registered_student, temp_admin):
    client.post("/logout")
    client.post("/login", json={"username": temp_admin["username"], "password": temp_admin["password"]})

    members = client.get("/admin/members").get_json()
    assert any(m["username"] == registered_student["username"] for m in members)


def test_admin_members_search_filter(client, registered_student, temp_admin):
    client.post("/logout")
    client.post("/login", json={"username": temp_admin["username"], "password": temp_admin["password"]})

    resp = client.get(f"/admin/members?search={registered_student['username']}")
    assert resp.status_code == 200
    results = resp.get_json()
    assert all(m["username"] == registered_student["username"] for m in results)

    no_match = client.get("/admin/members?search=definitely-not-a-real-username")
    assert no_match.get_json() == []
