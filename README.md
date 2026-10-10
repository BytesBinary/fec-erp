# FEC ERP

A college ERP for Faridpur Engineering College: people, results and CGPA, official result sync from the university portal, clearance with verifiable certificates, halls and library, routines and exams, account security, an AI layer (MCP server and in-app assistant) and a configurable email notification system.

Built with **Laravel 12, Filament 5, Livewire 4, Pest 4, Tailwind 4**. Nine roles (super admin, administration office, head of institution, principal, department head, hall provost, librarian, teacher, student) share one authorization layer used by the screens, the 130-tool MCP server and the assistant.

## Quick start

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate     # set DB_* (MySQL, database fec_erp)
php artisan migrate && php artisan db:seed
composer run dev                                      # server + queue worker + logs + Vite
```

Demo data with a login for every role (password `password`; **drops all tables**): `php artisan seed:demo --fresh`, then open `http://127.0.0.1:8000` and sign in as `superadmin@fec.test`, `office@fec.test`, `student.eligible@fec.test`, …

Result pulls and emails need a queue worker and the scheduler (`php artisan schedule:work`). Real email needs `NOTIFICATION_DRIVER=mail` and the `MAIL_*` settings.

## Documentation

Start at **[docs/README.md](docs/README.md)**:

- [Complete feature list](docs/FEATURES.md) · [Presentation kit](docs/PRESENTATION.md) · [Workflow diagrams](docs/WORKFLOWS.md)
- [Install, configure, operate](docs/OPERATIONS.md) · [Roles and permissions](docs/ROLES_AND_PERMISSIONS.md) · [Data model](docs/DATA_MODEL.md)
- [MCP tool catalog](docs/MCP_TOOLS.md) and [how to connect an AI client](README_MCP.md) · [Email events](docs/EMAIL_EVENTS.md)
- [Architecture notes](docs/ARCHITECTURE_NOTES.md) · [Design decisions](docs/DECISIONS.md) · [Independent review](docs/REVIEW.md)

## Tests

```bash
.autopilot/verify.sh          # build, fresh migrate + seed, Pint, Unit, Feature, MCP and browser tests
```

Unit 86 · Feature 495 · MCP 82 · Browser 31, run in CI (`.github/workflows/tests.yml`).
