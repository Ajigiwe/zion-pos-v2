<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

return new class extends Migration
{
    /**
     * Adds `username` (lowercase, unique) so staff sign in with
     * username + password instead of email + password.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->unique()->after('email');
        });

        $emails = DB::table('users')->whereNotNull('email')->orderBy('id')->pluck('email');

        collect($emails)
            ->map(fn ($email) => $this->slug(Str::of($email)->before('@')->value()))
            ->pipe(fn (Collection $slugs) => $this->uniqueSlugs($slugs))
            ->each(fn ($slug, $i) => DB::table('users')
                ->where('email', $emails[$i])
                ->update(['username' => $slug]));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    private function slug(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = (string) preg_replace('/[^a-z0-9._-]+/', '', $s);

        return $s === '' ? 'user' : $s;
    }

    /** Resolve collisions with a -2, -3 … suffix, preserving order. */
    private function uniqueSlugs(Collection $slugs): Collection
    {
        $base = [];
        $taken = collect(DB::table('users')->whereNotNull('username')->pluck('username')->all());

        return $slugs->map(function ($slug) use (&$base, $taken) {
            $out = $slug;
            $n = 2;
            while ($taken->contains($out) || collect($base)->contains($out)) {
                $out = $slug.'-'.($n++);
            }
            $base[] = $out;

            return $out;
        })->values();
    }
};
