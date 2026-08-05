# Base image: Python 3.14, matching the venv this project was actually
# developed and pytest-tested against (see .python-version / dev machine).
# "-slim" trims Debian packages not needed to run Python, keeping the image small.
FROM python:3.14-slim

# Fail fast on `pip install` errors instead of silently continuing (Debian/apt
# default shell doesn't do this; explicit here since we don't run apt in this
# image, but keeps behavior consistent if apt steps are ever added later).
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Don't write .pyc files (no benefit in a container that's rebuilt from source
# every deploy) and flush stdout/stderr immediately so `docker logs`/Coolify's
# log viewer show Flask/Gunicorn output in real time instead of buffered.
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

# All subsequent instructions run from here; also becomes the app's working
# directory at runtime (Flask resolves templates/static relative to this).
WORKDIR /app

# Copy just the dependency list first so this layer (and the slow `pip install`
# below) is cache-reused on rebuilds where only application code changed.
COPY requirements.txt .

# --no-cache-dir: don't keep pip's download cache in the image layer (smaller
# image; a container rebuild pulls fresh wheels anyway, there's nothing to reuse).
RUN pip install --no-cache-dir -r requirements.txt

# Now copy the rest of the application (app.py, db.py, templates/, static/, ...).
# .dockerignore keeps secrets/dev-only files (.env, venv/, Student.xlsx, ...)
# out of this — see that file for the full list and why.
COPY . .

# Run as a non-root user inside the container. The app itself doesn't need
# root (no privileged ports, no system package installs at runtime); this
# only limits blast radius if a dependency ever has an RCE-style bug.
# --create-home: Gunicorn 26's control server writes to a path under $HOME on
# startup — without a real home directory this fails with a (harmless, but
# scary-looking and log-spamming) "Permission denied: '/home/appuser'" error
# on every boot. A real home directory avoids that noise entirely.
RUN useradd --create-home --shell /usr/sbin/nologin appuser
USER appuser

# Documents which port the app listens on; Coolify still does its own port
# mapping in the UI regardless of this line — EXPOSE is metadata, not enforcement.
EXPOSE 5000

# Production entrypoint: Gunicorn, not `python app.py` — the dev server
# (Werkzeug) isn't meant for production use (no process management, weaker
# concurrency, prints a startup warning to that effect).
#
# --workers 1 is required, not a tuning choice: this app starts an in-process
# APScheduler background job (auto-checkout) and an in-memory Flask-Limiter
# store on import (see app.py). Both are per-process state — more than one
# worker would mean more than one copy of the scheduler job running
# concurrently (duplicate auto-checkout rows) and rate-limit counters that
# don't agree with each other across workers. Shell form (not exec-array form)
# so `${PORT:-5000}` is expanded by the shell; Coolify (or any PaaS) that
# injects a PORT env var is picked up automatically, otherwise it falls back
# to 5000 to match the dev server's default.
CMD gunicorn --workers 1 --bind 0.0.0.0:${PORT:-5000} app:app
