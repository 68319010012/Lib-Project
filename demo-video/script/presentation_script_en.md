# Presentation Script — Library Usage Monitoring & Statistics System
### English Voice-over + English Subtitles

**Presenters:** Mr. Kongphop Tipprasertsuk · Mr. Khachon Phansimahamat · Mr. Wachiramaytee Cameviangjun
**Total runtime:** 8 minutes 21 seconds (measured from the rendered audio, not estimated)
**Voice:** Microsoft Edge TTS — en-US-ChristopherNeural, rate −4%

## Delivered files

| File | What it is |
|---|---|
| `demo-video/voice/en/presentation_full_en.mp3` | Full narration, all 12 scenes in order (8:21) |
| `demo-video/voice/en/s01_opening.mp3` … `s12_conclusion.mp3` | One file per scene, for re-recording a single scene without redoing the rest |
| `demo-video/subtitles/presentation_en.srt` | 116 English subtitle cues, timed against the rendered audio |

## Measured scene durations

| Scene | Audio file | Duration |
|---|---|---|
| 1 Opening | `s01_opening.mp3` | 24.82 s |
| 2 Background | `s02_background.mp3` | 40.08 s |
| 3 Objectives | `s03_objectives.mp3` | 55.37 s |
| 4 Architecture | `s04_architecture.mp3` | 67.51 s |
| 5 Student workflow | `s05_workflow.mp3` | 53.28 s |
| 6 Operational features | `s06_features.mp3` | 26.76 s |
| 7 Duration & auto check-out | `s07_duration.mp3` | 45.17 s |
| 8 Administrative impact | `s08_admin.mp3` | 47.35 s |
| 9 Dashboard trends | `s09_dashboard.mp3` | 31.27 s |
| 10 Role access | `s10_roles.mp3` | 35.81 s |
| 11 Roadmap | `s11_roadmap.mp3` | 46.46 s |
| 12 Conclusion | `s12_conclusion.mp3` | 27.05 s |
| | **Total** | **500.93 s** |

> **Accuracy note.** This script was written against the actual codebase, not only the
> slide deck. Three claims in the original brief did not exist in the running system and
> have been corrected here and in the Canva deck: there is no QR code anywhere in the
> project, there is no Excel import screen in the admin area, and the login page is
> shared by students and staff rather than being two separate pages. Everything below is
> verifiable in the source.

---

## Scene 1 — Opening and Project Introduction

**Estimated Duration:** 0:00 – 0:24 (24.8 seconds, measured)

**Visual Direction:**
Slide 1. Title card on white with the project name centred, presenter names beneath.
Hold the slide still; no motion is needed while the greeting lands.

**Voice-over:**
Good afternoon. Today we are presenting the Library Usage Monitoring and Statistics
System — a web application built to record, monitor, and analyse how our college library
is actually used. Over the next few minutes we will walk through the problem that
started this project, the objectives we set, the technology behind the system, and how
both students and library staff use it day to day.

**English Subtitles:**
`0:00–0:06` Good afternoon. Today we are presenting the Library Usage Monitoring and Statistics System.
`0:06–0:12` A web application built to record, monitor, and analyse how our college library is actually used.
`0:12–0:18` Over the next few minutes we will walk through the problem that started this project,
`0:18–0:24` the objectives we set, the technology behind the system, and how students and staff use it day to day.

**On-Screen Text:**
Library Usage Monitoring & Statistics System

**Transition:** Simple cut to Slide 2.

---

## Scene 2 — Background and Problem

**Estimated Duration:** 0:24 – 1:04 (40.1 seconds, measured)

**Visual Direction:**
Slide 2. Section title with the one-line summary underneath.

**Voice-over:**
Traditionally, library attendance is recorded by hand. A student signs a paper logbook
on the way in, and staff copy those entries later if a report is needed. That approach
works, but it has real limits. Handwriting can be difficult to read, entries can be
skipped when the desk is busy, and nobody can answer a simple question like "how many
students are in the library right now" without walking around and counting. Totals for a
whole semester mean adding up pages by hand. Our project replaces that logbook with a
centralised web system, so that every entry and exit is recorded the moment it happens.

**English Subtitles:**
`0:24–0:28` Traditionally, library attendance is recorded by hand.
`0:28–0:35` A student signs a paper logbook on the way in, and staff copy those entries later if a report is needed.
`0:35–0:38` That approach works, but it has real limits.
`0:38–0:44` Handwriting can be difficult to read, and entries can be skipped when the desk is busy.
`0:44–0:50` Nobody can answer a simple question like how many students are in the library right now,
`0:50–0:52` without walking around and counting.
`0:52–0:56` Totals for a whole semester mean adding up pages by hand.
`0:56–1:00` Our project replaces that logbook with a centralised web system,
`1:00–1:04` so that every entry and exit is recorded the moment it happens.

**On-Screen Text:**
Project Objectives & Background

**Transition:** Fade to Slide 3.

---

## Scene 3 — Core System Objectives

**Estimated Duration:** 1:04 – 2:00 (55.4 seconds, measured)

**Visual Direction:**
Slide 3. Reveal each of the three numbered objectives as it is spoken.

**Voice-over:**
We set three objectives for this system.

The first is to develop the web application itself — a single place where students record
their visits and where staff manage the library. Because it runs in a browser, students
use it on their own phones with nothing to install.

The second objective is systematic data management. Every member record and every visit
is stored in a database rather than on paper, so information can be searched, verified,
and reviewed later. Staff can filter records by department, by academic year, or by a
range of dates, and get an answer immediately.

The third objective covers administration. The system gives library staff a dashboard
showing current occupancy and usage statistics, and it generates reports from the stored
data — which can be printed as PDF, or downloaded as CSV and Excel files.

**English Subtitles:**
`1:04–1:07` We set three objectives for this system.
`1:07–1:11` The first is to develop the web application itself,
`1:11–1:16` a single place where students record their visits and where staff manage the library.
`1:16–1:22` Because it runs in a browser, students use it on their own phones with nothing to install.
`1:22–1:26` The second objective is systematic data management.
`1:26–1:31` Every member record and every visit is stored in a database rather than on paper,
`1:31–1:36` so information can be searched, verified, and reviewed later.
`1:36–1:41` Staff can filter records by department, by academic year, or by a range of dates,
`1:41–1:43` and get an answer immediately.
`1:43–1:46` The third objective covers administration.
`1:46–1:52` The system gives library staff a dashboard showing current occupancy and usage statistics,
`1:52–1:55` and it generates reports from the stored data,
`1:55–2:00` which can be printed as PDF, or downloaded as CSV and Excel files.

**On-Screen Text:**
01 — Web Application Development
02 — Systematic Data Management
03 — Administration, Dashboard & Reports

**Transition:** Slide push to Slide 4.

---

## Scene 4 — System Architecture and Technology Stack

**Estimated Duration:** 2:00 – 3:07 (67.5 seconds, measured)

**Visual Direction:**
Slide 4. Bring in each technology layer in turn — presentation, application, data, reporting.

**Voice-over:**
Let me explain how the system is built, layer by layer.

What the user sees is built with HTML5 and Tailwind CSS, with plain JavaScript handling
the interaction. The layout is responsive, so the same pages work on a phone at the
library entrance and on a desktop at the staff counter.

Behind that, the application runs on PHP 8 on an Apache server, using a front-controller
pattern — every request enters through a single file that decides which page or which
API endpoint should handle it. This is also the layer that checks whether a user is
logged in and whether they are allowed to see what they are asking for.

The data sits in MySQL, in a database named `library_checkin`. All access goes through
PHP Data Objects with prepared statements, which keeps the values a user types separate
from the SQL itself — a standard protection against SQL injection.

Finally, printable reports are produced with the mPDF library, together with PHP's GD
extension for image handling.

**English Subtitles:**
`2:00–2:04` Let me explain how the system is built, layer by layer.
`2:04–2:11` What the user sees is built with HTML5 and Tailwind CSS, with plain JavaScript handling the interaction.
`2:11–2:17` The layout is responsive, so the same pages work on a phone at the library entrance
`2:17–2:20` and on a desktop at the staff counter.
`2:20–2:26` Behind that, the application runs on PHP 8 on an Apache server, using a front controller pattern.
`2:26–2:29` Every request enters through a single file
`2:29–2:34` that decides which page or which API endpoint should handle it.
`2:34–2:38` This is also the layer that checks whether a user is logged in
`2:38–2:42` and whether they are allowed to see what they are asking for.
`2:42–2:47` The data sits in MySQL, in a database named library_checkin.
`2:47–2:51` All access goes through PHP Data Objects with prepared statements,
`2:51–2:56` which keeps the values a user types separate from the SQL itself,
`2:56–2:59` a standard protection against SQL injection.
`2:59–3:03` Finally, printable reports are produced with the mPDF library,
`3:03–3:07` together with the PHP GD extension for image handling.

**On-Screen Text:**
Frontend · HTML5 · Tailwind CSS · JavaScript
Backend · PHP 8.x · Apache · Front Controller
Database · MySQL / MariaDB · PDO · Prepared Statements
Reporting · mPDF · GD

**Transition:** Fade to Slide 5.

---

## Scene 5 — Student Login and Check-In Workflow

**Estimated Duration:** 3:07 – 4:01 (53.3 seconds, measured)

**Visual Direction:**
Slide 5. Reveal the three steps left to right as each is described.

**Voice-over:**
Now let us follow a student through the system.

A student opens the library portal in any browser — usually on their phone as they walk
in. They sign in with their student ID and password. If it is their first visit, they can
register their profile in the same place: name, department, level, and year.

Once signed in, the main screen shows a single large check-in button. The student presses
it, chooses roughly how long they plan to stay, and confirms. The system records the
exact time and stores the visit. If the same student presses check-in again while already
checked in, the system will not create a duplicate entry — it knows they are already
inside, and offers check-out instead.

When they leave, they press the same button to check out, and the visit is closed.

**English Subtitles:**
`3:07–3:11` Now let us follow a student through the system.
`3:11–3:17` A student opens the library portal in any browser, usually on their phone as they walk in.
`3:17–3:20` They sign in with their student ID and password.
`3:20–3:26` If it is their first visit, they can register their profile in the same place:
`3:26–3:28` name, department, level, and year.
`3:28–3:33` Once signed in, the main screen shows a single large check-in button.
`3:33–3:39` The student presses it, chooses roughly how long they plan to stay, and confirms.
`3:39–3:42` The system records the exact time and stores the visit.
`3:42–3:47` If the same student presses check-in again while already checked in,
`3:47–3:50` the system will not create a duplicate entry.
`3:50–3:55` It knows they are already inside, and offers check-out instead.
`3:55–4:01` When they leave, they press the same button to check out, and the visit is closed.

**On-Screen Text:**
1. Open Web Portal → 2. Authenticate → 3. Check-In / Out

**Transition:** Cut to Slide 6.

---

## Scene 6 — Key Operational Features

**Estimated Duration:** 4:01 – 4:27 (26.8 seconds, measured)

**Visual Direction:**
Slide 6. Section divider with the summary line beneath the heading.

**Voice-over:**
Those steps are simple on the surface, but several features work together behind them to
keep the records reliable. The system manages how long each visit lasts, separates what
students may do from what staff may do, keeps a live view of who is currently inside,
prevents duplicate or unfinished entries, and preserves the full history of every visit.
The next few sections look at the most important of these in more detail.

**English Subtitles:**
`4:01–4:03` Those steps are simple on the surface,
`4:03–4:08` but several features work together behind them to keep the records reliable.
`4:08–4:11` The system manages how long each visit lasts,
`4:11–4:14` separates what students may do from what staff may do,
`4:14–4:17` keeps a live view of who is currently inside,
`4:17–4:23` prevents duplicate or unfinished entries, and preserves the full history of every visit.
`4:23–4:27` The next few sections look at the most important of these in more detail.

**On-Screen Text:**
Key Operational Features

**Transition:** Fade to Slide 7.

---

## Scene 7 — Smart Duration and Automatic Check-Out

**Estimated Duration:** 4:27 – 5:12 (45.2 seconds, measured)

**Visual Direction:**
Slide 7. Three stacked points revealed in order, with the library photograph on the right.

**Voice-over:**
When a student checks in, they choose a planned duration — for example, two hours. The
system will not accept a time that runs past the library's closing hour, so a planned
visit always ends within opening times.

If they need longer, they can extend the session by thirty or sixty minutes, again within
opening hours.

The most useful part is what happens when someone forgets. In a paper logbook, a student
who leaves without signing out simply leaves an open, unfinished line. Here, the system
closes the visit automatically at closing time. That means the history contains complete
visits with a start and an end, and the average duration statistics stay meaningful.

**English Subtitles:**
`4:27–4:33` When a student checks in, they choose a planned duration, for example, two hours.
`4:33–4:38` The system will not accept a time that runs past the closing hour,
`4:38–4:41` so a planned visit always ends within opening times.
`4:41–4:47` If they need longer, they can extend the session by thirty or sixty minutes,
`4:47–4:48` again within opening hours.
`4:48–4:52` The most useful part is what happens when someone forgets.
`4:52–4:57` In a paper logbook, a student who leaves without signing out
`4:57–4:59` simply leaves an open, unfinished line.
`4:59–5:04` Here, the system closes the visit automatically at closing time.
`5:04–5:09` That means the history contains complete visits with a start and an end,
`5:09–5:12` and the average duration statistics stay meaningful.

**On-Screen Text:**
Planned Usage Duration · Extension Requests · Automatic Check-Out

**Transition:** Slide push to Slide 8.

---

## Scene 8 — Administrative Impact and Data Integrity

**Estimated Duration:** 5:12 – 6:00 (47.4 seconds, measured)

**Visual Direction:**
Slide 8. The large "100% Digital Traceability" figure on the left, supporting points on the right.

**Voice-over:**
For library staff, the change is that the handwritten logbook is replaced by database
records. Staff can see current occupancy at any moment, open any student's attendance
history, and compare usage between departments.

The system also records failed login attempts in a dedicated table, which supports
rate-limiting — repeated wrong passwords are slowed down rather than allowed to continue
indefinitely. Changes to the database structure are tracked in a migrations table, so the
schema can be updated in a controlled way.

The figure shown here, one hundred percent digital traceability, describes the design
goal of this project: that every recorded visit exists as a digital record that can be
traced, rather than as a line of handwriting.

**English Subtitles:**
`5:12–5:18` For library staff, the handwritten logbook is replaced by database records.
`5:18–5:23` Staff can see current occupancy at any moment, open any attendance history,
`5:23–5:25` and compare usage between departments.
`5:25–5:30` The system also records failed login attempts in a dedicated table,
`5:30–5:32` which supports rate limiting.
`5:32–5:37` Repeated wrong passwords are slowed down rather than allowed to continue indefinitely.
`5:37–5:42` Changes to the database structure are tracked in a migrations table,
`5:42–5:45` so the schema can be updated in a controlled way.
`5:45–5:50` The figure shown here, one hundred percent digital traceability,
`5:50–5:52` describes the design goal of this project:
`5:52–5:57` that every recorded visit exists as a digital record that can be traced,
`5:57–6:00` rather than as a line of handwriting.

**On-Screen Text:**
100% Digital Traceability — project design goal
login_attempts · schema_migrations

**Transition:** Fade to Slide 9.

---

## Scene 9 — Real-Time Dashboard and Attendance Trends

**Estimated Duration:** 6:00 – 6:31 (31.3 seconds, measured)

**Visual Direction:**
Slide 9. Animate the four horizontal bars — morning, midday, afternoon, evening — in sequence.

**Voice-over:**
Because every visit is stored with a timestamp, the dashboard can group them and show
when the library is busiest across the day — morning, midday, afternoon, and evening.

The values on this slide are illustrative, shown to demonstrate how the chart presents a
busy midday period against a quieter evening. The point is the capability: once real
attendance data accumulates, staff can see genuine patterns and use them when planning
seating or deciding when more staff are needed at the desk.

**English Subtitles:**
`6:00–6:03` Because every visit is stored with a timestamp,
`6:03–6:08` the dashboard can group them and show when the library is busiest across the day:
`6:08–6:11` morning, midday, afternoon, and evening.
`6:11–6:13` The values on this slide are illustrative,
`6:13–6:19` shown to demonstrate how the chart presents a busy midday period against a quieter evening.
`6:19–6:21` The point is the capability.
`6:21–6:26` Once real attendance data accumulates, staff can see genuine patterns
`6:26–6:31` and use them when planning seating or deciding when more staff are needed at the desk.

**On-Screen Text:**
Hourly Library Attendance Trends
Illustrative values

**Transition:** Cut to Slide 10.

---

## Scene 10 — User Role Access Matrix

**Estimated Duration:** 6:31 – 7:07 (35.8 seconds, measured)

**Visual Direction:**
Slide 10. Highlight the Student column, then the Library Admin column.

**Voice-over:**
Not everyone sees the same system. Students can check in and out, and view their own
attendance history — nothing more. They cannot open the dashboard, browse other students'
records, or export reports.

Library staff have full access: the dashboard and analytics, member management, the
complete attendance history, and report export in PDF, CSV, and Excel. The login page is
the same for both — the system reads the role attached to the account and decides what
that person is allowed to reach.

**English Subtitles:**
`6:31–6:34` Not everyone sees the same system.
`6:34–6:40` Students can check in and out, and view their own attendance history, nothing more.
`6:40–6:45` They cannot open the dashboard, browse other records, or export reports.
`6:45–6:51` Library staff have full access: the dashboard and analytics, member management,
`6:51–6:57` the complete attendance history, and report export in PDF, CSV, and Excel.
`6:57–7:00` The login page is the same for both.
`7:00–7:03` The system reads the role attached to the account
`7:03–7:07` and decides what that person is allowed to reach.

**On-Screen Text:**
Student Role · Restricted
Library Admin / Staff · Full Access

**Transition:** Slide push to Slide 11.

---

## Scene 11 — Implementation Roadmap

**Estimated Duration:** 7:07 – 7:53 (46.5 seconds, measured)

**Visual Direction:**
Slide 11. Draw the timeline left to right, revealing each phase marker in turn.

**Voice-over:**
We built the system in four phases.

Phase one was the database — designing the tables for students, users, and check-in logs,
and setting up migrations so the structure could change safely later.

Phase two was the core web application: the PHP backend, session-based authentication,
the check-in flow, and the Tailwind interface.

Phase three added the administrative side — the dashboard, mPDF report export, and
rate-limiting on login.

Phase four was testing and deployment. We tested locally with XAMPP, and deployment to
the live server runs through GitHub Actions, so a release is a controlled, repeatable
step rather than copying files by hand.

**English Subtitles:**
`7:07–7:09` We built the system in four phases.
`7:09–7:16` Phase one was the database: designing the tables for students, users, and check-in logs,
`7:16–7:21` and setting up migrations so the structure could change safely later.
`7:21–7:27` Phase two was the core web application: the PHP backend, session-based authentication,
`7:27–7:31` the check-in flow, and the Tailwind interface.
`7:31–7:36` Phase three added the administrative side: the dashboard, mPDF report export,
`7:36–7:38` and rate limiting on login.
`7:38–7:43` Phase four was testing and deployment. We tested locally with XAMPP,
`7:43–7:48` and deployment to the live server runs through GitHub Actions,
`7:48–7:53` so a release is a controlled, repeatable step rather than copying files by hand.

**On-Screen Text:**
Phase 1 Database · Phase 2 Core Web · Phase 3 Admin Suite · Phase 4 Deployment

**Transition:** Fade to Slide 12.

---

## Scene 12 — Conclusion and Questions

**Estimated Duration:** 7:53 – 8:20 (27.1 seconds, measured)

**Visual Direction:**
Slide 12. "Questions & Answers" centred, thank-you line beneath. Hold to the end.

**Voice-over:**
In conclusion, the Library Usage Monitoring and Statistics System replaces a paper
logbook with a working web application. Students check in and out from their own phones,
every visit is stored in a central database, staff can see who is in the library right
now, and the system turns that stored data into statistics and printable reports.

Thank you for your attention. We are now ready to answer your questions.

**English Subtitles:**
`7:53–7:58` In conclusion, the Library Usage Monitoring and Statistics System
`7:58–8:01` replaces a paper logbook with a working web application.
`8:01–8:05` Students check in and out from their own phones,
`8:05–8:08` every visit is stored in a central database,
`8:08–8:11` staff can see who is in the library right now,
`8:11–8:16` and the system turns that stored data into statistics and printable reports.
`8:16–8:20` Thank you for your attention. We are now ready to answer your questions.

**On-Screen Text:**
Questions & Answers
Thank you for reviewing the Library Usage Monitoring & Statistics System

**Transition:** Hold on final slide.

---

## Corrections applied to the Canva deck

| Slide | Was | Now | Why |
|---|---|---|---|
| 5 | "Student Entry & QR Code Workflow" / "Entrance Scan" / QR icon | "Student Login & Check-In Workflow" / "Open Web Portal" / globe icon | No QR code exists anywhere in the codebase — verified by search |
| 10 | "Member Management & Excel Import" | "Member Management and Roster Records" | No Excel import screen in the admin area; roster loading is a back-end script |
| 11 | "Built PHP 8.x backend, authentication, QR flow, and Tailwind CSS UI" | "…session authentication, check-in flow, and Tailwind CSS UI" | Same reason as slide 5 |

Everything else in the deck matched the code and was left unchanged.
