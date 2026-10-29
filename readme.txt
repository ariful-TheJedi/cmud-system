
database
====================
3 core foundations(table list)
-------------------------------
1. Institute & Branches 
2. Students & Academy
3.Finance

For CMUD, follow this order for making the database
======================================================
1. Finalize the schema: tables, columns, primary keys, foreign keys, and relationship rules.
2. Create table migrations: include primary keys and reference columns such as student_id.
3. Create relationship migrations: add foreign key constraints after all referenced tables exist.
4. Create Eloquent models: define matching hasMany(), belongsTo(), and other relationships.
5. Run php artisan migrate: Laravel executes table migrations first, then relationship migrations,
       