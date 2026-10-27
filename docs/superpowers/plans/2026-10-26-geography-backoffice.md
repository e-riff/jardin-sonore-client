# Geography Backoffice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move regions, departments, and municipalities from EasyAdmin menus into one Technique → Géographie area, opening on a responsive, searchable, server-paginated municipality index.

**Architecture:** Add a geography route family and local tabs for communes, departments, and regions. Use Symfony UX LiveComponents for AJAX filters/search/sort/pagination; query municipality index rows through DBAL projections that exclude GeoJSON. Keep entity forms and relations on Doctrine, and render the current municipality contour and center through the existing Symfony UX Map/Leaflet stack in read-only mode.

**Tech Stack:** Symfony 8.1, Doctrine ORM/DBAL, Symfony Forms, Symfony UX LiveComponent, Stimulus, Symfony UX Map/Leaflet, Twig, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-26-geography-backoffice-design.md`

## Global Constraints

- No schema, import, or synchronization changes.
- No changes to current values or geographic relationships.
- Do not load all municipalities or GeoJSON to build the index or filter tree.
- Municipality index pagination is server-side, 50 rows per page by default.
- Municipality first load selects Loire department code `42`; explicit reset shows all municipalities.
- Municipality search includes name, INSEE code, postal code, and department code/name; sort defaults to name ascending.
- The geographic tree permits region-wide and department-level selection, including mixed selections.
- Department search uses name and code; region search uses name only; both update through AJAX.
- Municipality contour and center are read-only in the Technique UI.
- Keep EasyAdmin CRUD controllers available for any legacy association, autocomplete, and filter usages; remove their navigation entries only after the replacement works.
- Preserve access controls, CSRF protection, validation, and relation constraints for create/edit/delete actions.

## Review Focus

- Large municipality GeoJSON values must never be selected for index rows or filter-tree metadata; add a query-level assertion for the list projection.
- Mixed region/departement selections must return exactly the selected departments and preserve the selection through AJAX pagination and URL state.
- Forged sort-column/direction input must fall back to safe whitelisted values; cover it in the table component tests.
- A region or department with dependants must not be deleted; show an understandable validation message and preserve the row.
- Missing/invalid GeoJSON or center coordinates must not break municipality edit; render an explanatory empty state in place of the map.

---

## File Structure

- `src/Application/Controller/MunicipalityManagementController.php`: municipality index entry, create, edit, and delete endpoints.
- `src/Application/Controller/DepartmentManagementController.php`: department index and CRUD endpoints.
- `src/Application/Controller/RegionManagementController.php`: region index and CRUD endpoints.
- `src/Application/Form/MunicipalityManagementType.php`, `DepartmentManagementType.php`, `RegionManagementType.php`: validated Symfony forms preserving current editable fields and relations.
- `src/Application/Twig/Component/MunicipalityTable.php`: bounded DBAL index query and LiveComponent state/actions.
- `src/Application/Twig/Component/DepartmentTable.php`, `RegionTable.php`: AJAX search and ordered reference indexes.
- `src/Application/Twig/Component/GeographyMunicipalityMap.php` or a controller map builder: map model centered on the municipality with its read-only contour and center marker.
- `assets/controllers/geography_filter_tree_controller.js`: synchronize parent/child checkboxes and indeterminate region state.
- `assets/controllers/geography_map_controller.js`: draw the single municipality contour and center on the existing Leaflet map without edit controls.
- `templates/components/MunicipalityTable.html.twig`, `DepartmentTable.html.twig`, `RegionTable.html.twig`: responsive tables and controls.
- `templates/technical/geography/`: index and edit templates with local Communes / Départements / Régions navigation.
- `templates/internal/base.html.twig`: add the single Technique → Géographie navigation item and active state.
- `translations/technical+intl-icu.fr.yaml`: all labels, confirmations, validation and empty/error states.
- Verification for this lot is static (backend lint/static analysis, Twig lint, asset syntax/build, and Doctrine schema diff); no test files are added or executed in this session.

### Task 1: Municipality Index Query and Live Table

**Files:**
- Create: `src/Application/Twig/Component/MunicipalityTable.php`
- Create: `templates/components/MunicipalityTable.html.twig`
- Create: `tests/Functional/Application/Twig/Component/GeographyTablesTest.php`

**Interfaces:**
- Produces paged row arrays containing only municipality id, name, INSEE code, postal code, department code, and department name.
- Live state: `query`, `selectedDepartmentCodes`, `showAllDepartments`, `sortBy`, `sortDirection`, `page`.

- [ ] Define the LiveComponent state for default Loire selection, explicit all-department reset, search, sorting, and server-side pagination.
- [ ] Implement the component with DBAL query builders, separate total-count query, 50-row limit, whitelisted sort mapping, stable id tiebreaker, and parameterized department-code list.
- [ ] Inspect the generated query and component state to confirm `geo_shape` is never selected and invalid sort state falls back to name ascending.

### Task 2: Hierarchical Region/Department Filter

**Files:**
- Modify: `src/Application/Twig/Component/MunicipalityTable.php`
- Modify: `templates/components/MunicipalityTable.html.twig`
- Create: `assets/controllers/geography_filter_tree_controller.js`
- Modify: `tests/Functional/Application/Twig/Component/GeographyTablesTest.php`

**Interfaces:**
- The component supplies bounded tree metadata (region code/name and department code/name only).
- The Stimulus controller synchronizes region selection to child department checkboxes and sets indeterminate state when only some children are selected.

- [ ] Specify the tree state transitions for selecting a region, multiple departments across regions, partial parent state, and clearing the selection.
- [ ] Implement the tree with live checkbox state and preserve selection, page reset, and URL state through component refreshes.
- [ ] Inspect the generated query and rendered tree to confirm mixed selections are bounded to selected departments and tree metadata excludes municipality geometry; run `node --check assets/controllers/geography_filter_tree_controller.js`.

### Task 3: Municipality CRUD and Read-Only Map

**Files:**
- Create: `src/Application/Controller/MunicipalityManagementController.php`
- Create: `src/Application/Form/MunicipalityManagementType.php`
- Create: `templates/technical/geography/municipalities.html.twig`
- Create: `templates/technical/geography/municipality_edit.html.twig`
- Create: `assets/controllers/geography_map_controller.js`
- Modify: `tests/Functional/Application/Controller/GeographyManagementControllerTest.php`
- Modify: `translations/technical+intl-icu.fr.yaml`

- [ ] Map the existing routes, fields, validation, and department relation into the new controller and form.
- [ ] Implement CRUD with existing entity fields (name, INSEE code, postal code, department, address, phone, email, SIREN, SIRET); keep GeoJSON and center out of editable fields.
- [ ] Build a read-only map from one municipality’s GeoJSON and stored center, with an explanatory empty state if geometry is unavailable; do not include geometry in list queries.
- [ ] Protect deletion with CSRF and confirmation; map foreign-key/relation failures to a translated validation message without cascading deletes.
- [ ] Run Twig lint for the new templates and inspect valid, missing, and invalid GeoJSON display paths.

### Task 4: Department and Region CRUD with AJAX Search

**Files:**
- Create: `src/Application/Controller/DepartmentManagementController.php`
- Create: `src/Application/Controller/RegionManagementController.php`
- Create: `src/Application/Form/DepartmentManagementType.php`
- Create: `src/Application/Form/RegionManagementType.php`
- Create: `src/Application/Twig/Component/DepartmentTable.php`
- Create: `src/Application/Twig/Component/RegionTable.php`
- Create: `templates/components/DepartmentTable.html.twig`
- Create: `templates/components/RegionTable.html.twig`
- Create: `templates/technical/geography/departments.html.twig`
- Create: `templates/technical/geography/regions.html.twig`
- Modify: `tests/Functional/Application/Controller/GeographyManagementControllerTest.php`
- Modify: `tests/Functional/Application/Twig/Component/GeographyTablesTest.php`

- [ ] Map the existing department and region fields, validations, and relation constraints into the new forms and tables.
- [ ] Implement department and region pages using the compact existing table/action/dialog patterns; show department code before name and sort by code, region columns/form retain code and name.
- [ ] Implement parameterized AJAX searches and create/edit forms; department form keeps its region association.
- [ ] Add confirmed deletion with clear constraint errors and no cascade behavior.
- [ ] Inspect rendered pages and route configuration for name/code search scopes, relation preservation, and delete constraint feedback.

### Task 5: Navigation, Local Geography Tabs, EasyAdmin Menu Cleanup

**Files:**
- Modify: `templates/internal/base.html.twig`
- Modify: `src/Infrastructure/Admin/DashboardController.php`
- Modify: `templates/technical/geography/municipalities.html.twig`
- Modify: `templates/technical/geography/departments.html.twig`
- Modify: `templates/technical/geography/regions.html.twig`
- Modify: `translations/technical+intl-icu.fr.yaml`
- Modify: `tests/Functional/Application/Controller/GeographyManagementControllerTest.php`

- [ ] Implement the shared horizontal local tabs under each geography page title, reusing the standard SVG action icons, titles, and accessible labels.
- [ ] Remove Regions, Departments, and Municipalities from the EasyAdmin menu only; keep their CRUD classes/routes for dependencies and existing relation forms.
- [ ] Inspect nav active states, entry targets, and retained EasyAdmin association/autocomplete routes; run Twig lint.

### Task 6: API Inventory and Final Verification

**Files:**
- Modify: `ROADMAP.md`
- No source-code integration for other public APIs in this task.

- [ ] Summarize the current Geo API fields and suitable Culture open-data sources separately from this transfer; identify promising data without importing it or changing schema.
- [ ] Run `git diff --check`.
- [ ] Run `make backend-lint`.
- [ ] Run Doctrine schema diff and confirm no schema change is generated.
- [ ] Run Twig lint, `node --check` for new Stimulus controllers, and the backend production asset build if required by the repository scripts.
- [ ] Update ROADMAP.md with the geography transfer complete and record the next user-facing follow-up for public API enrichment.
