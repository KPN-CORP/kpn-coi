<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * non_employees (profiles of contracted non-employees, managed under
 * Credentials) was created by hand on the deployed databases, so a database
 * built from migrations had no such table.
 *
 * This creates it as deployed, minus user_id and location_id: the two
 * migrations right after this one add those, as they did on the deployed
 * databases. Skipped where the table already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('non_employees')) {
            return;
        }

        Schema::create('non_employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 25)->nullable();
            $table->string('fullname', 100);
            $table->enum('gender', ['Male', 'Female'])->nullable();
            $table->string('email', 100);
            $table->string('group_company', 50)->nullable();
            $table->string('designation')->nullable();
            $table->string('designation_code')->nullable();
            $table->string('designation_name')->nullable();
            $table->string('job_level', 50)->nullable();
            $table->string('company_name', 50)->nullable();
            $table->string('contribution_level_code')->nullable();
            $table->string('work_area_code', 50)->nullable();
            $table->string('office_area', 50)->nullable();
            $table->string('manager_l1_id', 25)->nullable();
            $table->string('manager_l2_id', 25)->nullable();
            $table->enum('employee_type', ['Permanent', 'Contract', 'Probation', 'Service Bond'])->nullable();
            $table->string('unit')->nullable();
            $table->string('personal_email', 100)->nullable();
            $table->string('personal_mobile_number', 25)->nullable();
            $table->string('whatsapp_number', 25)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth', 50)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->string('citizenship_status', 50)->nullable();
            $table->string('ethnic_group', 50)->nullable();
            $table->string('homebase', 100)->nullable();
            $table->text('current_address')->nullable();
            $table->string('current_city', 50)->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('permanent_city', 50)->nullable();
            $table->string('blood_group', 50)->nullable();
            $table->string('tax_status', 50)->nullable();
            $table->string('bpjs_tk', 50)->nullable();
            $table->string('bpjs_ks', 50)->nullable();
            $table->string('ktp', 50);
            $table->string('kk', 50)->nullable();
            $table->string('npwp', 50)->nullable();
            $table->string('mother_name', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 50)->nullable();
            $table->date('date_of_joining')->nullable();
            $table->string('users_id', 36)->nullable();
            $table->longText('access_menu')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Named as on the deployed databases.
            $table->unique('ktp', 'employees_ktp_unique');
        });
    }

    public function down(): void
    {
        // Only a table this migration created could be dropped safely, and
        // there is no telling that apart afterwards: leave it.
    }
};
