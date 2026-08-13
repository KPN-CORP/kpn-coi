<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups the several (user_id, type) identities that belong to one person.
 *
 * A declaration is owned by the pair (user_id, type) -- see CoiDeclaration.
 * One person can hold many such pairs over time:
 *
 *   - a non_employee login created by an admin, and
 *   - one or more employee stints, each with its own kpncorp employee_id,
 *     chained backward by employees.`old_employee_id_(rehired)`.
 *
 * This table is purely additive: it never moves, rewrites or deletes a
 * coi_declarations row. It is a read-side grouping layer. If it is empty or
 * wrong, DeclarationScopeService falls back to "self only" -- today's
 * behaviour -- so a bad backfill can never lose or crash a declaration, only
 * temporarily narrow a person's own history until the sync re-runs.
 *
 * Both users tables auto-increment from 1, so user_id overlaps across types;
 * the (type, user_id) pair is the real key, never user_id alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaration_identities', function (Blueprint $table) {
            $table->id();

            // The person this identity belongs to. No FK constraint is added:
            // keeping it constraint-free lets the backfill truncate and rebuild
            // persons + identities together without ordering headaches, and the
            // grouping is always rewritten as a set by IdentityLinkService.
            $table->unsignedBigInteger('person_id')->index();

            // Which database user_id points at: 'employee' -> kpncorp.users.id,
            // 'non_employee' -> local users.id.
            $table->enum('type', ['employee', 'non_employee']);

            $table->unsignedBigInteger('user_id');

            // HRIS employee_id snapshot for the stint (null for a pure
            // non-employee identity). Kept so a link survives the later
            // deletion of the HRIS row it was resolved from.
            $table->string('employee_id')->nullable();

            // The person's current identity -- the latest employee stint, or
            // the non-employee login if they were never an employee. Exactly
            // one row per person_id carries this.
            $table->boolean('is_primary')->default(false);

            // How the link was established, for auditing the grouping:
            //   hris_rehire            -> walked old_employee_id_(rehired)
            //   non_employee_conversion-> the convert-to-employee dialog
            //   self                   -> a standalone identity, no link yet
            $table->string('source')->default('self');

            // Admin who created a manual link, when applicable.
            $table->unsignedBigInteger('linked_by')->nullable();

            $table->timestamps();

            // One row per identity: an identity belongs to exactly one person.
            // This is also what makes the sync/backfill idempotent (upsert).
            $table->unique(['type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaration_identities');
    }
};
