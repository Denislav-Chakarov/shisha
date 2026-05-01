# Project Handbook

Use this as the starting point for all future development tasks.

## Core Documents
- System map and end-to-end behavior: `docs/full-project-knowledge-audit.md`
- Prioritized risk register with repro steps: `docs/risk-audit.md`
- Test roadmap and confidence milestones: `docs/test-backlog-confidence-ladder.md`

## How to Use This Handbook
1. Read `full-project-knowledge-audit.md` before changing routes/controller logic.
2. Check `risk-audit.md` for regressions likely to affect live restaurant operations.
3. Pick tests from `test-backlog-confidence-ladder.md` before or alongside feature work.
4. After each meaningful feature/bugfix, update the three docs if behavior/risk profile changed.

## Maintenance Rules
- Keep architecture and flow notes synchronized with:
  - `routes/web.php`
  - `app/Http/Controllers/DashboardController.php`
  - `app/Http/Controllers/Auth/LoginController.php`
  - `database/migrations/*.php`
  - `resources/views/*.blade.php`
- Do not remove historical risk entries; mark them mitigated with date and fix reference.
- Treat this handbook as the primary context recovery source when chat history is unavailable.

