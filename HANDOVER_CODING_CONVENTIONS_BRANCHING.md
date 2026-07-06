# kpn-coi - Handover Document: Coding Conventions & Branching Strategy

**Project Name:** kpn-coi
**Tech Stack:** Laravel 12 + Vue 3 + Inertia.js + Vite + Tailwind CSS + PHP 8.2

---

## 1. Coding Conventions

### A. Backend Environment
- **Architecture & Design Patterns:** The project follows a standard Laravel MVC structure with a service layer for business logic. Controllers in the app/Http/Controllers directory handle request entry points, while business logic is kept in app/Services. Validation is handled through request classes in app/Http/Requests, and response shaping is handled through resources in app/Http/Resources.
- **Naming Conventions:**
  - **Variables & Functions:** Uses camelCase. Example: $this->authUser and saveDraft() in app/Http/Controllers/Employee/DeclarationController.php.
  - **Classes & Controllers:** Uses PascalCase. Example: DeclarationController in app/Http/Controllers/Employee/DeclarationController.php.
  - **Database Tables:** Uses snake_case, plural table names. Example: coi_declarations in database/migrations/2026_06_17_074224_create_coi_declarations_table.php.
- **Code Formatting:** PHP styling is handled via Laravel Pint, which is listed in composer.json. Run ./vendor/bin/pint before committing.

### B. Frontend Environment
- **Component Structure:** Reusable UI components are stored in resources/js/Components, while page-level screens live in resources/js/Pages. Layouts are separated in resources/js/Layouts.
- **Naming Conventions:**
  - **Component Files:** Uses PascalCase. Example: resources/js/Pages/Employee/DeclarationForm.vue.
  - **State & Functions:** Uses camelCase. Example: const submitted = ref(false) and usePreviousData() in resources/js/Pages/Employee/DeclarationForm.vue.
- **State Management:** The frontend uses Vue 3 Composition API with local state via ref, computed, and watch, plus Inertia page props. No Pinia or Vuex store layer was found.
- **Linter & Formatter:** No dedicated ESLint or Prettier config files were found in the repository; the current frontend setup is based on Vue SFC conventions and Vite.

### C. API Standards & Documentation
- **API Response Structure:** This project is primarily Inertia-based rather than a separate REST API. The common backend payload shape for resource-based responses is represented in app/Http/Resources/DeclarationResource.php, for example:

```json
{
  "id": 1,
  "period": 2026,
  "status": "draft",
  "submitted_at": null,
  "created_at": "2026-06-17T00:00:00.000000Z",
  "responses_count": 0,
  "responses": {
    "question_key": "response_value"
  }
}
```

- **Notes:** Inertia passes these objects as page props to Vue components rather than returning a standalone JSON API contract.

---

## 2. Git & Branching Strategy
- **Repository Remote:** The repository is connected to GitHub via the origin remote pointing to https://github.com/KPN-CORP/kpn-coi.git.
- **Current Branch:** The active branch in the workspace is main.
- **Branching Strategy:** The repository appears to follow a simple mainline/trunk-based workflow centered on main. There is no evidence of a custom branch naming convention, release branch model, or branch protection configuration in the repo itself.
- **CI/CD:** No GitHub Actions or other CI pipeline configuration files were found in the repository, so there is currently no automated pipeline defined here.
- **Recommended Local Validation Before Merge:** Run composer test, npm run build, and ./vendor/bin/pint before handing off changes.

---

## 3. Practical Development Notes
- Backend changes typically belong in app/Http/Controllers, app/Services, app/Http/Requests, and app/Models.
- Frontend changes typically belong in resources/js/Pages, resources/js/Components, and resources/js/Layouts.
- Database changes should be implemented through Laravel migrations under database/migrations.
- For new features, follow the existing pattern of keeping controller logic thin and moving business rules into services.
