# CMUD ERP — Database Agent Instructions

## 1. Purpose

This file defines instructions for AI agents working with database seeding in the CMUD ERP project.

Follow these instructions when planning, creating, updating, executing or verifying database seeders.

## 2. Technology

- Backend: Laravel
- Database: PostgreSQL
- Testing: Pest
- Architecture: Modular Laravel
- Environment: Development

## 3. General Rules

1. Always inspect existing migrations, models and database relationships before generating data.
2. Never invent columns, tables, constraints or business rules.
3. Follow existing database relationships and application logic.
4. Generate realistic, fictional Bangladeshi operational data.
5. Use Laravel Seeders directly.
6. Do not create or use Model Factories.
7. Keep seed data deterministic and reproducible.
8. Do not modify migrations or existing application logic without explicit approval.
9. Do not delete or overwrite unrelated existing records.
10. Never use destructive database commands without explicit authorization.

## 4. Required Documentation

Before implementing or updating seeders, read:

- `database/SEED_DATA_PLAN.md`
- `database/SEED_SCENARIOS.md`

### Responsibility

**SEED_DATA_PLAN.md**
Defines the tables, data quantities, dependencies, insertion order and seed-data specifications.

**SEED_SCENARIOS.md**
Defines realistic business scenarios, associated records, expected results and verification criteria.

Do not duplicate detailed scenario definitions inside this file.

## 5. Agent Workflow

Follow this order:

1. Analyze relevant migrations, models and business rules.
2. Review existing seeders and database records.
3. Review and update the seed-data plan if necessary.
4. Review and update relevant business scenarios.
5. Implement seeders according to the documented specifications.
6. Validate the code and database environment.
7. Execute seeders only against an authorized development database.
8. Verify inserted data and business consistency.
9. Run relevant Pest tests against a separate test database.
10. Report the results.

Stop before implementation when the task explicitly requests planning only.

## 6. Seeder Rules

- Create maintainable, module-organized Laravel Seeders.
- Write predefined sample records directly inside Seeder files.
- Use stable unique identifiers for important records.
- Resolve foreign keys from actual existing records.
- Respect unique constraints, required columns and business rules.
- Use transactions when inserting related records where appropriate.
- Make seeders rerunnable without unintended duplicates.
- Avoid duplicating financial transactions and stock movements.
- Do not silently alter real or manually created data.
- Leave temporary authentication tables empty unless required for a specific scenario.

## 7. Data Quality Rules

All seed data must be:

- Realistic
- Relationally valid
- Logically consistent
- Predictable
- Useful for development and testing
- Free from real personal or confidential records

Financial calculations, academic progression and inventory movements must follow the actual application rules.

Do not insert invalid records simply to test validation errors. Test those cases in isolated Pest tests.

## 8. Database Safety

Before seeding:

1. Verify the Laravel environment.
2. Verify the database connection and name.
3. Confirm the database is an authorized development database.
4. Inspect existing data for potential conflicts.

Never execute `migrate:fresh`, `db:wipe`, uncontrolled truncation or destructive cleanup without explicit authorization.

Never execute development seeders against production.

## 9. Verification Rules

After inserting seed data, verify:

- Expected record counts
- Foreign-key integrity
- Unique constraints
- Business relationships
- Financial calculation consistency
- Academic record consistency
- Stock movement consistency
- Documented scenario outcomes

A successful seeder execution alone is not sufficient verification.

Use SQL queries and Pest tests where appropriate.

## 10. Change Management

When adding or modifying seed data:

1. Identify the affected tables and scenarios.
2. Update the relevant documentation.
3. Modify only the necessary seeders.
4. Preserve unrelated records.
5. Verify that existing scenarios remain valid.
6. Report changes and any unresolved issues.

Do not regenerate the entire database unnecessarily.

## 11. Reporting Format

After each seeding task, provide:

- Tables analyzed
- Files created or modified
- Records inserted or updated
- Scenarios implemented
- Verification performed
- Pest test results
- Errors or limitations

Clearly distinguish planned work from executed and verified work.

## 12. Additional Instructions

Add new agent instructions below this section as the project evolves.

### 12.1 — Reserved for future instructions

No additional instructions currently defined.