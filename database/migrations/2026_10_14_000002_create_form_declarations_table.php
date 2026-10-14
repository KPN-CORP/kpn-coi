<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_type_id')
                ->constrained('form_types')
                ->restrictOnDelete();
            $table->foreignId('template_id')
                ->constrained('form_templates')
                ->restrictOnDelete();
            // Same identity rule as coi_declarations: `type` decides which
            // database user_id points at (employee -> kpncorp.users.id,
            // non_employee -> local users.id), so no foreign key here. Not to
            // be confused with form_type_id, which says *which form* this is.
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20);
            $table->year('period');
            $table->string('business_unit')->nullable();
            // draft: read and agreed to, not signed yet (no signature or
            // snapshot). signed: final. Signing turns the draft row into the
            // signed one, so the unique key below holds for both.
            $table->string('status', 10)->default('signed');
            $table->char('locale', 2)->default('en');
            $table->timestamp('agreed_at')->nullable();
            $table->string('signed_name')->nullable();
            // The variable values as they were at signing, so the signed
            // document still reads the same after the HRIS data changes.
            $table->json('variables')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            // One signature per person, per form type, per period.
            $table->unique(['user_id', 'type', 'form_type_id', 'period'], 'form_declarations_signer_unique');
            $table->index(['form_type_id', 'period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_declarations');
    }
};
