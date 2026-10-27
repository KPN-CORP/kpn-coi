<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_type_id')
                ->constrained('form_types')
                ->restrictOnDelete();
            $table->year('period');
            $table->string('title_en');
            $table->string('title_id');
            $table->longText('content_en');
            $table->longText('content_id');
            // Repeated on every page of the PDF, one per language like the body.
            $table->longText('header_en')->nullable();
            $table->longText('header_id')->nullable();
            $table->longText('footer_en')->nullable();
            $table->longText('footer_id')->nullable();
            // The paper is the same physical page in both languages. Margins
            // are millimetres from the paper edge to the body text; the header
            // and footer sit inside the top and bottom margins.
            $table->string('paper_size', 10)->default('a4');
            $table->string('orientation', 10)->default('portrait');
            $table->decimal('margin_top', 5, 1)->default(30);
            $table->decimal('margin_right', 5, 1)->default(12.7);
            $table->decimal('margin_bottom', 5, 1)->default(20);
            $table->decimal('margin_left', 5, 1)->default(12.7);
            // Default font of the document; the editor can still override
            // family/size per piece of text. Keys from config('forms.fonts').
            $table->string('font_family', 30)->default('dejavu-sans');
            $table->decimal('font_size', 4, 1)->default(10);
            $table->decimal('line_height', 3, 2)->default(1.5);
            // Business unit names (group_company). Null or empty = every unit.
            $table->json('business_units')->nullable();
            $table->dateTime('publish_at');
            $table->boolean('is_active')->default(true);
            // kpncorp.users.id -- soft links, the users table lives in the HRIS
            // database so no foreign key is possible.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_type_id', 'period', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_templates');
    }
};
