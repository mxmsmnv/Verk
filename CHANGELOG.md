# Changelog

## [1.3.3] - 2026-06-18

### Fixed
- Standalone Verk TinyMCE editors now render the ProcessWire `.InputfieldTinyMCE` wrapper with `data-settings`, so height/resize/menu settings are actually available to `InputfieldTinyMCE.js`.
- Module version bumped to `133` so ProcessWire detects the `1.3.3` patch upgrade.

## [1.3.2] - 2026-06-18

### Fixed
- Verk TinyMCE editors now override the Insert menu as well as the toolbar, so ProcessWire image picker actions are not exposed without page/image-field context.
- Module version bumped to `132` so ProcessWire detects the `1.3.2` patch upgrade.

## [1.3.1] - 2026-06-18

### Fixed
- Rich-text editors now pass height and resize behavior through ProcessWire's `InputfieldTinyMCE` settings instead of forcing TinyMCE container height with CSS, so the Knowledge Base editor no longer collapses after initialization and the resize handle can work.
- Module version bumped to `131` so ProcessWire detects the `1.3.1` patch upgrade.

## [1.3.0] - 2026-06-18

### Added
- Task assignment can now be scoped to selected ProcessWire roles so assignee dropdowns and filters stay manageable on sites with large frontend user bases.

### Changed
- Assignee name lookups now load only referenced users instead of scanning broad user lists.
- Module version bumped to `130` so ProcessWire detects the `1.3.0` minor upgrade.

## [1.2.3] - 2026-06-18

### Changed
- Knowledge Base note editor now opens taller by default so longer notes are easier to edit when TinyMCE resizing is unavailable.
- Module version bumped to `123` so ProcessWire detects the `1.2.3` patch upgrade.

## [1.2.2] - 2026-06-15

### Fixed
- Page Editor Widget no longer appears in field-scoped PageEdit dialogs such as ProcessImageLibrary image/file edit modals.
- Dashboard linked-page labels now use the null-safe enriched page title to avoid PHP 8.1+ deprecation warnings.
- Module version bumped to `122` so ProcessWire detects the `1.2.2` patch upgrade.

## [1.2.1] - 2026-06-14

### Changed
- Tasks with linked ProcessWire pages now show a stable page label, keep an edit action visible, and only show the public view action when the page is viewable.
- Calendar task deadlines now include assignee names and can be filtered by assignee.
- Task estimate options now include `1, 2, 4, 6, 8, 12, 16, 24, 32, 40` hours.
- Task edit sidebar width and warning-count contrast were adjusted for readability.
- Rich-text editors in Verk use a task-safe TinyMCE toolbar without image-picker actions.
- Module version bumped to `121` so ProcessWire detects the `1.2.1` patch upgrade.

### Fixed
- Fixed PHP 8.1+ deprecation warnings from passing null page titles into `htmlspecialchars()` and `mb_strimwidth()`.
- Content Audit now skips pages that do not have the audited field instead of reporting system/admin pages that cannot satisfy the rule.

## [1.2.0] - 2026-06-06

### Added
- Unified modern admin UI for Dashboard, Tasks, Calendar, Content Audit, Knowledge Base, Sprints, Settings, and all create/edit forms
- Dashboard with compact metrics, assigned tasks, recent open tasks, content audit status, upcoming publications, and sprint planning
- Sprints: quarter overview, missing-date filter, planning shortcuts, sprint health, task assignment panel, existing-task picker via JavaScript, and DOCX export
- Calendar: month, week, and quarter views; task due dates are shown together with configured publication dates
- Enterprise quarter planning: configurable fiscal quarter start month, quarter filters, quarter labels, and quarter-aware task/sprint forms
- Page Editor Widget settings: position, collapsed state, limit, sort, empty state, create link, status/priority/due date/quarter/assignee toggles, plus live JavaScript preview
- TinyMCE integration for task descriptions, task comments, note content, and sprint goals when `InputfieldTinyMCE` is installed
- Knowledge Base search/category filtering, rich note summaries, return-aware note edit/create flows, and export actions
- Audit dot-notation field paths (`field.subfield`, `items.*.amount`) with clearer invalid-field warnings
- Bulk task creation from audit results with return-aware navigation
- Time Log panel redesign and task discussion author metadata
- Return-aware create/edit flows for tasks, notes, sprints, and bulk audit task creation
- Test coverage expanded to 55 smoke/contract checks
- Package metadata now includes the declared MIT `LICENSE` file
- Added `.gitignore` rules for local/editor artifacts and temporary package noise

### Changed
- Tabs, filters, action buttons, cards, forms, empty states, and panel spacing now share one visual language across the module
- Task, sprint, note, and settings forms were compressed and reorganized so the primary work fits better on visible screens
- Tasks filters now use the same segmented pattern as Content Audit
- Responsive behavior was tightened for desktop, tablet, and narrow mobile widths, including Tasks/Sprints filters and Calendar month/week/quarter tables
- Export links are presented as buttons
- `View Task` / `View Note` edit pages now use `Edit Task` / `Edit Note`
- Settings page now explains calendar source, audit rules location, and widget behavior more clearly
- README updated for current features, quarter planning, rich text, DOCX exports, and the 4-column audit rule format
- Internal release QA and roadmap sections were removed from README for a cleaner public package
- Module version bumped to `120` so ProcessWire detects the `1.2.0` upgrade

### Fixed
- Session-expired failure after task creation
- `Pages::findById()` exception by using safe batch page loading
- Empty DATE SQL errors when optional date fields are blank
- Task owner and FK validation for task/sprint/time-log writes
- Assignee selection in task forms
- Active tab state on sprint create/edit pages
- Calendar publication-source notice spacing
- Unwanted UI link underlines while preserving rich-text link underlines
- Safe return URL handling across create/edit/delete/export routes
- Missing task/note/sprint edit records now redirect back to safe list contexts instead of silently landing on confusing stale edit URLs
- Mobile overflow in Tasks/Sprints filters, calendar tables, and audit code examples
- DOCX export routing and service integration

## [1.1.1] - 2026-05-24

### Added
- ProcessWire i18n coverage: user-facing strings are wrapped with `__()` in views/export code and `$this->_()` in the module; JavaScript strings are passed through `json_encode(__())`
- Translated enum label helpers: `statusLabel()`, `priorityLabel()`, and `sprintStatusLabel()` centralize labels instead of duplicating them in templates

### Changed
- Aligned the admin UI with the ProcessWire design system by removing hex/rgba fallbacks from `var(--pw-*)` tokens in `views/_layout.php`; colors and spacing now come from Konkat AdminThemeUikit tokens

### Security
- Added the `verk` permission through both `permission` and `permissions` module metadata; access is no longer based only on view access to the admin page, while superusers still pass automatically

### Fixed
- `___upgrade()` now runs `VerkDB::migrate()` during module upgrades, so existing installations receive `vk_sprints`, `vk_time_logs`, and new `vk_tasks` columns
- `___upgrade()` creates the `verk` permission for installations upgrading from older versions
- Module version bumped from `107` to `108` so the upgrade hook runs after Modules > Refresh

## [1.1.0] - 2026-05-23

### Security
- Added owner checks through `requireOwner()` for `actionDeleteTask`, `actionDeleteNote`, and `actionDeleteSprint`; superusers bypass, other users must be the creator
- Added `user_id` owner validation for `actionDeleteTimeLog`
- Graceful CSRF handling now reports an error and redirects instead of throwing `WireException`

### Added
- Foreign-key validation on save: `fwPageExists`, `fwUserExists`, `fwSprintExists`, and `fwTaskExists`
- Pagination in `viewTasks` via `page` and `limit` GET parameters, with template navigation
- Pagination in `viewDashboard` via `my_page` and `recent_page`
- `getCalendarItems()` limit parameter with default `100`
- N+1 fix: `enrichTaskPagesBatch()` batch-loads linked pages with one `pages->findById()` call
- Extracted `VerkExportService.php`; DOCX export methods moved out of the module class
- `VerkTest.php` smoke tests for syntax, DDL validation, and reflection checks

### Fixed
- Fixed syntax issues in `views/tasks.php`
- Added `$total = $total ?? 0` fallback in `views/tasks.php`

## [1.0.0] - 2026-03-08

### Added
- Tasks with `page_id` link to PW pages: live page picker, "Edit in PW" and "View on site" buttons
- Page Editor Widget: task list injected into ProcessPageEdit via hook
- Task list view with status/priority filters
- Editorial Calendar reads real PW page date fields from a configurable template and field
- Calendar shows both page publications and task deadlines on the same grid
- Content Audit: PW-selector-based rules with plain text configuration
- "Create Task" shortcut from audit result rows with prefilled page context
- Knowledge Base notes with categories, plain text body, and preview
- Dashboard stats, assigned tasks, upcoming publications, and audit summary
- Settings page for calendar template/date field configuration
- CSRF protection on all state-changing actions
- SQL tables: `vk_tasks`, `vk_comments`, and `vk_notes`
