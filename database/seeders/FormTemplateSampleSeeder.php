<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FormTemplate;
use App\Models\FormType;
use App\Services\FormHtmlSanitizer;
use Illuminate\Database\Seeder;

/**
 * Inserts the sample templates from config/form_template_samples.php for the
 * current period. They go in **inactive** so they never collide with the
 * "one active template per business unit" rule nor reach signers before an
 * admin has reviewed and switched one on. Re-running skips titles that already
 * exist for the period.
 *
 *   php artisan db:seed --class=FormTemplateSampleSeeder
 */
class FormTemplateSampleSeeder extends Seeder
{
    public function run(FormHtmlSanitizer $sanitizer): void
    {
        $period = (int) now()->year;

        foreach (config('form_template_samples', []) as $sample) {
            $formType = FormType::query()->where('code', $sample['form_type'] ?? '')->first();

            if (! $formType) {
                $this->command?->warn("Skipped \"{$sample['title_en']}\": form type \"".($sample['form_type'] ?? '').'" not found.');

                continue;
            }

            FormTemplate::query()->firstOrCreate(
                ['form_type_id' => $formType->id, 'period' => $period, 'title_en' => $sample['title_en']],
                [
                    'title_id' => $sample['title_id'],
                    'content_en' => $sanitizer->clean($sample['content_en']),
                    'content_id' => $sanitizer->clean($sample['content_id']),
                    'header_en' => $sanitizer->clean($sample['header_en'] ?? '') ?: null,
                    'header_id' => $sanitizer->clean($sample['header_id'] ?? '') ?: null,
                    'footer_en' => $sanitizer->clean($sample['footer_en'] ?? '') ?: null,
                    'footer_id' => $sanitizer->clean($sample['footer_id'] ?? '') ?: null,
                    'paper_size' => $sample['paper_size'] ?? 'a4',
                    'orientation' => $sample['orientation'] ?? 'portrait',
                    'margin_top' => $sample['margin_top'] ?? 30,
                    'margin_right' => $sample['margin_right'] ?? 12.7,
                    'margin_bottom' => $sample['margin_bottom'] ?? 20,
                    'margin_left' => $sample['margin_left'] ?? 12.7,
                    'font_family' => $sample['font_family'] ?? 'dejavu-sans',
                    'font_size' => $sample['font_size'] ?? 10,
                    'line_height' => $sample['line_height'] ?? 1.5,
                    'business_units' => null,
                    'publish_at' => now(),
                    'is_active' => false,
                ]
            );
        }
    }
}
