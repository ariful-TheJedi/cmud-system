4 core module list
-------------------------------
1. Institute & Branches
2. Students & Academy
3. Finance
4. Seccurity & System User management

CMUD application folder structure
----------------------------------

```text
cmud-system/
|-- app/                         # Application code
|   |-- Actions/Fortify/         # Existing Fortify authentication actions
|   |-- Concerns/                # Shared traits and reusable concerns
|   |-- Console/Commands/        # Custom Artisan commands
|   |-- Http/                    # HTTP-related code
|   |   |-- Controllers/Settings/ # Existing settings controllers
|   |   |-- Middleware/          # HTTP middleware
|   |   `-- Requests/Settings/   # Existing settings validation requests
|   |-- Models/                  # All Eloquent models and relationships
|   |-- Modules/                 # Business modules
|   |   |-- Auth/                # Authentication module extensions
|   |   |-- AccessControl/       # Permissions and access control
|   |   |-- Institute/           # Institutes and branches
|   |   |-- Academy/             # Students, courses, and enrollments
|   |   `-- Finance/             # Tuition, expenses, and finance workflows
|   `-- Providers/               # Application and module service providers
|       |-- AppServiceProvider.php
|       |-- FortifyServiceProvider.php
|       `-- ModuleServiceProvider.php
|-- database/                    # Database schema and seed data
|   |-- factories/               # Model factories for development and tests
|   |-- migrations/              # Database schema changes
|   `-- seeders/                 # Database seed data
|-- routes/                      # Application route definitions
|   |-- api.php
|   |-- web.php
|   |-- console.php
|   `-- settings.php
|-- tests/                       # Pest test suite
|   |-- Feature/                 # HTTP and feature tests
|   |   |-- Auth/
|   |   |-- AccessControl/
|   |   |-- Institute/
|   |   |-- Academy/
|   |   |-- Finance/
|   |   `-- Settings/
|   |-- Integration/             # Workflows spanning multiple modules
|   |-- Unit/                    # Isolated logic tests
|   |   |-- Institute/
|   |   |-- Academy/
|   |   `-- Finance/
|   |-- Pest.php
|   `-- TestCase.php
`-- resources/                   # Frontend assets and Blade views
    |-- css/                     # Stylesheets
    |-- js/                      # React/TypeScript frontend
    `-- views/                   # Blade templates
```

Each business module uses this structure as needed:

```text
app/Modules/{Module}/
|-- Actions/       # Business operations
|-- Controllers/   # API request handlers
|-- Policies/      # Authorization rules
|-- Requests/      # Input validation
|-- Resources/     # JSON response formatting
`-- routes.php     # Routes owned by this module
```

All Eloquent models belong in `app/Models/`. Keep migrations, factories, and seeders
in Laravel's standard `database/` directories.
