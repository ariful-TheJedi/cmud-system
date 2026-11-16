<?php

namespace Database\Seeders\Development;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

trait UsesCoreSeedData
{
    protected const SEEDED_AT = '2026-11-01 09:00:00';

    /** @param array<string, mixed> $identity @param array<string, mixed> $attributes */
    protected function saveRow(string $table, array $identity, array $attributes): object
    {
        $query = DB::table($table);
        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first();
        $values = array_merge($identity, $attributes, [
            'created_at' => $existing->created_at ?? self::SEEDED_AT,
            'updated_at' => self::SEEDED_AT,
        ]);

        if ($existing !== null) {
            if (isset($existing->id)) {
                DB::table($table)->where('id', $existing->id)->update($values);

                return DB::table($table)->where('id', $existing->id)->first();
            }

            DB::table($table)->where($identity)->update($values);

            return DB::table($table)->where($identity)->first();
        }

        if (isset($identity['id'])) {
            DB::table($table)->insert($values);

            return DB::table($table)->where('id', $identity['id'])->first();
        }

        if (! Schema::hasColumn($table, 'id')) {
            DB::table($table)->insert($values);

            return DB::table($table)->where($identity)->first();
        }

        $id = DB::table($table)->insertGetId($values);

        return DB::table($table)->where('id', $id)->first();
    }

    /** @param array<string, mixed> $identity */
    protected function findId(string $table, array $identity): int
    {
        $query = DB::table($table);
        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        $id = $query->value('id');

        if ($id === null) {
            throw new RuntimeException("Missing seeded parent row in [{$table}].");
        }

        return (int) $id;
    }

    protected function seedUser(string $email, array $attributes): object
    {
        $existing = DB::table('users')->where('email', $email)->first();
        $values = array_merge($attributes, [
            'email' => $email,
            'updated_at' => self::SEEDED_AT,
        ]);

        if ($existing !== null) {
            DB::table('users')->where('id', $existing->id)->update($values);

            return DB::table('users')->where('id', $existing->id)->first();
        }

        $values['password'] = bcrypt(Str::random(64));
        $values['remember_token'] = null;
        $values['created_at'] = self::SEEDED_AT;

        $id = DB::table('users')->insertGetId($values);

        return DB::table('users')->where('id', $id)->first();
    }

    protected function scenarioNote(string $scenarioId, string $detail): string
    {
        return "{$detail} [CMUD-CORE:{$scenarioId}]";
    }
}
