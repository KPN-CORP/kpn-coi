# Using reference-source

This folder is a snapshot of every file behind the kpn-coi letter / form
template editor, at the same paths as in the original project
(branch `features/integrity-pact-form`, 19 October 2026). It's meant for
porting the editor into another Laravel + Vue 3 project without opening kpn-coi.
It is **not** a package and won't run on its own: some files depend on
kpn-coi code that isn't included here.

Read `Letter-Editor-Implementation-Guide.docx` (one folder up) for *why*
things work the way they do. This README covers *how* to take the files over.

---

## 1. Three kinds of files

| Kind | Meaning | Files |
| --- | --- | --- |
| **A — Copy as is** | Self-contained. Only adjust a namespace or alias. | `useFormEditor.ts`, `EditorExtensions.ts`, `VariableNode.ts`, `ToolbarMenu.vue`, `FormDocument.vue`, `types.ts`, `FormHtmlSanitizer.php`, `FormImageService.php`, `FormImageController.php`, `form-editor.css`, `config/forms.php` (edit the variable list) |
| **B — Copy and adapt** | The editor logic is generic, but it calls kpn-coi helpers (locale, routes, UI kit, users). Swap those. | `DocumentEditor.vue`, `EditorToolbar.vue`, `VariablePicker.vue`, `FormVariableService.php`, `FormPdfService.php`, `pdf/form_declaration.blade.php`, `FormTemplate.php`, the migration, `SaveFormTemplateRequest.php` |
| **C — Reference only** | Full of kpn-coi rules (form types, business units, two databases, employee/non-employee, spatie permissions). Read them for the pattern and write your own. | `TemplateForm.vue`, `FormTemplateController.php`, `FormTemplateService.php`, `FormDeclarationService.php`, `SignatureModal.vue`, `config/form_template_samples.php`, `locales/forms.*.ts` |

---

## 2. Where each file goes in your project

The paths assume the usual Laravel + Inertia layout, with `@/` aliased to
`resources/js/`. If your layout is different, keep the same relative grouping.

| reference-source path | Target | Kind |
| --- | --- | --- |
| `resources/js/Components/Forms/useFormEditor.ts` | same | A |
| `resources/js/Components/Forms/EditorExtensions.ts` | same | A |
| `resources/js/Components/Forms/VariableNode.ts` | same | A |
| `resources/js/Components/Forms/types.ts` | same (drop the report/stats types you don't need) | A |
| `resources/js/Components/Forms/ToolbarMenu.vue` | same | A |
| `resources/js/Components/Forms/FormDocument.vue` | same | A |
| `resources/js/Components/Forms/VariablePicker.vue` | same | B |
| `resources/js/Components/Forms/EditorToolbar.vue` | same | B |
| `resources/js/Components/Forms/DocumentEditor.vue` | same | B |
| `resources/js/Components/Forms/SignatureModal.vue` | only if you need typed-name signing | C |
| `resources/js/Pages/Admin/Forms/TemplateForm.vue` | your own editor page | C |
| `resources/js/Config/locales/forms.en.ts` / `forms.id.ts` | the source of UI labels | C |
| `resources/css/form-editor.css` | import it from your CSS entry | A |
| `app/Services/FormHtmlSanitizer.php` | same | A |
| `app/Services/FormImageService.php` | same | A |
| `app/Http/Controllers/FormImageController.php` | same | A |
| `app/Services/FormVariableService.php` | same; rewrite `resolve()` | B |
| `app/Services/FormPdfService.php` | same; change the input type | B |
| `resources/views/pdf/form_declaration.blade.php` | same | B |
| `app/Models/FormTemplate.php` | same; drop the relations you don't have | B |
| `database/migrations/…create_form_templates_table.php` | new migration | B |
| `app/Http/Requests/Admin/SaveFormTemplateRequest.php` | same; drop the business-unit rules | B |
| `config/forms.php` | same | A |
| `app/Services/FormTemplateService.php`, `FormDeclarationService.php`, `Admin/FormTemplateController.php` | write your own (see §5) | C |

---

## 3. kpn-coi dependencies to replace (frontend)

The frontend files expect these things to exist. Either provide them under
the same names (quickest) or edit the imports.

### 3.1 `useLocale()` — `@/Composables/useLocale`

Used by `DocumentEditor`, `EditorToolbar`, `VariablePicker` (and the C files).
They only read `t.value.forms.*`, `t.value.common.*` and `locale.value`
(`'en' | 'id'`). A minimal shim:

```ts
// resources/js/Composables/useLocale.ts
import { computed, ref } from 'vue'
import { forms as formsEn } from './labels/forms.en'   // see below
import { forms as formsId } from './labels/forms.id'

const common = {
    en: { save: 'Save', cancel: 'Cancel', close: 'Close', saving: 'Saving…', noResults: 'No results' },
    id: { save: 'Simpan', cancel: 'Batal', close: 'Tutup', saving: 'Menyimpan…', noResults: 'Tidak ada hasil' },
}

const locale = ref<'en' | 'id'>('en')

export function useLocale() {
    const t = computed(() => locale.value === 'id'
        ? { forms: formsId, common: common.id }
        : { forms: formsEn, common: common.en })

    return { t, locale }
}
```

To build `labels/forms.en.ts`, take `resources/js/Config/locales/forms.en.ts`
from this folder, change its first line `forms: {` to
`export const forms = {` and its last line `},` to `}`. Do the same for `id`.
If your app has its own i18n (vue-i18n etc.), map those keys into it instead.

The keys the editor components actually use: `header`, `body`, `footer`,
`editingPart`, `undo`, `redo`, `paragraphStyle`, `styleNormal`,
`styleHeading1..3`, `fontFamily`, `defaultFont`, `fontSize`, `defaultSize`,
`bold`, `italic`, `underline`, `strikethrough`, `textColor`, `highlight`,
`noColor`, `alignment`, `alignLeft/Center/Right/Justify`, `bulletList`,
`numberedList`, `insert`, `insertTable`, `insertImage`, `insertLine`,
`insertTab`, `quote`, `more`, `superscript`, `subscript`, `clearFormatting`,
`insertVariable`, `variable`, `searchVariables`, `table`, `addRow`,
`addColumn`, `deleteRow`, `deleteColumn`, `mergeCells`, `rowHeight`,
`rowHeightHint`, `borderlessTable`, `deleteTable`, `columnResizeHint`,
`imageUploadFailed`, `imageRules`.

### 3.2 `route()` — Ziggy

`EditorToolbar.vue` uploads images with `route('admin.master.images.store')`.
Without Ziggy, replace it with the URL:

```ts
const response = await fetch('/admin/master/images', { … })
```

### 3.3 SweetAlert2

`EditorToolbar.vue` uses `Swal.fire` for the row-height prompt and upload
errors. Either `npm install sweetalert2`, or replace both with
`window.prompt` / `window.alert`, or with your own modal.

### 3.4 Font Awesome

Toolbar icons are `fa-solid fa-*` classes. Install
`@fortawesome/fontawesome-free` and import `css/all.min.css`, or swap the
`<i>` tags for your icon set.

### 3.5 Tailwind classes and CSS variables

The components use Tailwind utilities, plus three project tokens:

| Token | Used for | Fix |
| --- | --- | --- |
| `border-border` | borders of the toolbar/paper frame | add `colors: { border: '#e2e8f0' }` to `tailwind.config.js`, or replace with `border-slate-200` |
| `bg-primary`, `text-primary` | the Variable button, signature icon | add `colors: { primary: '#ab2f2b' }`, or replace with your brand colour |
| `var(--topbar-height)` | sticky toolbar offset in `DocumentEditor.vue` | define `:root { --topbar-height: 64px }` (your header height), or `0px` |

`form-editor.css` hard-codes the kpn-coi red `#ab2f2b` (active toolbar
buttons, chip outline, resize handles) and the `Figtree` font for zone labels.
Search and replace them to rebrand.

Without Tailwind, the components still work, but you'll need to write CSS for
the utility classes in their templates (layout, spacing, the dropdown panel).
`form-editor.css` itself does not depend on Tailwind.

### 3.6 Fonts

Load the signature font in your main layout if you use `{{signature}}`:

```html
<link href="https://fonts.googleapis.com/css2?family=Ephesis&display=swap" rel="stylesheet" />
```

---

## 4. kpn-coi dependencies to replace (backend)

| File | Depends on | Change |
| --- | --- | --- |
| `FormVariableService.php` | `User`, `NonEmployeeUser`, `Employee`, `NonEmployee`, `Companies`; the `$account->employee` relation | Rewrite `profileFor()`, `fullNameFor()` and `resolve()` to read your user/profile model. Keep `definitions()`, `render()`, `displayValues()`, `signatureHtml()`, `dateTime()` and `date()` as they are. |
| `FormPdfService.php` | `FormDeclaration`, `FormDeclarationService::renderSigned()` | Change `download()` to take whatever you render: a template plus a values array. Keep the dompdf options, `setPaper()` and `mmToPt()`. |
| `pdf/form_declaration.blade.php` | `$declaration` (not used in the markup) | Usable as is; it needs `$title`, `$content`, `$header`, `$footer`, `$page`, `$locale`. |
| `FormTemplate.php` | `FormType`, `FormDeclaration`, `$connection = 'mysql'` | Remove `formType()`, `declarations()`, `signedDeclarations()`, `scopeAvailable()` and `isLocked()` (or point them at your models), and remove `$connection`. Keep `PAPER_SIZES`, `pageLayout()`, `fontKey()`, `title()/header()/footer()/content()`. |
| Migration | `form_types` foreign key, `business_units`, `period` | Remove the columns you don't need. Keep the six HTML columns and the page-setup columns with their defaults. |
| `SaveFormTemplateRequest.php` | `FormTemplateService` (business units, conflicts) | Remove the `form_type_id`, `business_units*` rules and the conflict check. Keep the HTML, page-setup and font rules, plus the “`strip_tags` not empty” check in `after()`. |
| `FormImageService.php` | nothing | If your image route is not `/forms/images/…`, change `URL_PREFIX`. The sanitizer reads the same constant. |
| `FormImageController.php` | nothing | Register the routes in §5.2. |

---

## 5. Wiring it up

### 5.1 Order of work

1. **Install dependencies.**
   ```bash
   npm install @tiptap/core @tiptap/pm @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-text-align @tiptap/extension-text-style @tiptap/extension-table @tiptap/extension-image @tiptap/extension-subscript @tiptap/extension-superscript sweetalert2 @fortawesome/fontawesome-free
   ```
   ```bash
   composer require barryvdh/laravel-dompdf
   ```
2. **Backend, kind A first.** Copy `config/forms.php`, `FormHtmlSanitizer`,
   `FormImageService` and `FormImageController`, and register the image routes.
3. **Model and table.** Adapt the migration and `FormTemplate`, then run the
   migration.
4. **Variables.** Adapt `FormVariableService::resolve()` and edit the
   `variables` list in `config/forms.php` to match. Every key needs both a
   config entry and a value in `resolve()`, or it prints as “-”.
5. **Frontend shims.** Add `useLocale` and the labels, Tailwind tokens,
   `--topbar-height`, Font Awesome, Ephesis (§3).
6. **Frontend, kind A.** Copy `useFormEditor`, `EditorExtensions`,
   `VariableNode`, `types`, `ToolbarMenu` and `FormDocument`, and import
   `form-editor.css`.
7. **Frontend, kind B.** Copy `VariablePicker`, `EditorToolbar` and
   `DocumentEditor`, and fix the imports and the upload URL.
8. **Editor page and controller.** Write them yourself, using the minimal
   versions in §5.3–5.4 as a starting point.
9. **Rendering and PDF.** Use the render function in §5.5, then the blade and
   the adapted `FormPdfService`.
10. **Test.** Run the checks in §6.

### 5.2 Routes

```php
use App\Http\Controllers\FormImageController;

// Upload: only for people who may edit templates.
Route::middleware(['auth', 'can:edit-templates'])
    ->post('/admin/master/images', [FormImageController::class, 'store'])
    ->name('admin.master.images.store');

// Read: anyone signed in who may read a document with a header image.
Route::middleware('auth')
    ->get('/forms/images/{file}', [FormImageController::class, 'show'])
    ->where('file', '[A-Za-z0-9._-]+')
    ->name('forms.image');
```

### 5.3 The props the editor page needs

`DocumentEditor` needs `page`, `variables` and `fontOptions`.
`FormDocument` (the preview) needs `page` and rendered HTML. A minimal
controller method, condensed from `FormTemplateController::form()`:

```php
public function edit(Request $request, FormTemplate $template, FormVariableService $variables)
{
    $preview = [...$variables->resolve($request->user(), now()->year),
                'signed_name' => $variables->fullNameFor($request->user())];

    return Inertia::render('Templates/Edit', [
        'template'   => [...$template->only([
            'id', 'title_en', 'title_id', 'content_en', 'content_id',
            'header_en', 'header_id', 'footer_en', 'footer_id',
        ]), ...$template->pageLayout()],
        'paperSizes' => collect(FormTemplate::PAPER_SIZES)
            ->map(fn ($s, $k) => ['key' => $k, 'width' => $s[0], 'height' => $s[1]])->values(),
        'variables'  => collect($variables->definitions())
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        'fontOptions' => [
            'fonts' => collect(config('forms.fonts'))->map(fn ($f, $k) => ['key' => $k, ...$f])->values(),
            'sizes' => config('forms.font_sizes'),
            'lineHeights' => config('forms.line_heights'),
            'textColors' => config('forms.text_colors'),
            'highlightColors' => config('forms.highlight_colors'),
        ],
        'previewValues' => [
            'en' => $variables->displayValues($preview, 'en'),
            'id' => $variables->displayValues($preview, 'id'),
        ],
    ]);
}

public function update(SaveFormTemplateRequest $request, FormTemplate $template, FormHtmlSanitizer $clean)
{
    $data = $request->validated();
    foreach (['content_en', 'content_id', 'header_en', 'header_id', 'footer_en', 'footer_id'] as $field) {
        $data[$field] = $clean->clean((string) ($data[$field] ?? '')) ?: null;
    }
    $template->update($data);

    return back();
}
```

> Never save template HTML without running it through `FormHtmlSanitizer`.
> The HTML is later printed raw with `v-html` and in dompdf.

### 5.4 A minimal editor page

The core of `TemplateForm.vue`, without form types, business units, samples or
permissions:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DocumentEditor from '@/Components/Forms/DocumentEditor.vue'
import FormDocument from '@/Components/Forms/FormDocument.vue'
import type { FontOptions, FormVariable, PageLayout } from '@/Components/Forms/types'

const props = defineProps<{
    template: any
    paperSizes: { key: string, width: number, height: number }[]
    variables: FormVariable[]
    fontOptions: FontOptions
    previewValues: Record<'en' | 'id', Record<string, string>>
}>()

const form = useForm({ ...props.template })
const language = ref<'en' | 'id'>('en')
const showPreview = ref(false)

// Same rules as FormTemplate::pageLayout(), so the editor is to scale while editing.
const page = computed<PageLayout>(() => {
    const size = props.paperSizes.find(s => s.key === form.paper_size) ?? props.paperSizes[0]
    const landscape = form.orientation === 'landscape'
    const font = props.fontOptions.fonts.find(f => f.key === form.font_family) ?? props.fontOptions.fonts[0]
    return {
        paper_size: form.paper_size, orientation: landscape ? 'landscape' : 'portrait',
        width: landscape ? size.height : size.width, height: landscape ? size.width : size.height,
        margin_top: +form.margin_top, margin_right: +form.margin_right,
        margin_bottom: +form.margin_bottom, margin_left: +form.margin_left,
        font_family: form.font_family, font_css: font.css,
        font_size: +form.font_size, line_height: +form.line_height,
    }
})

// One error object per language, built once per change (not on every render).
const errors = computed(() => ({
    en: { header: form.errors.header_en, content: form.errors.content_en, footer: form.errors.footer_en },
    id: { header: form.errors.header_id, content: form.errors.content_id, footer: form.errors.footer_id },
}))

const fill = (html: string) => (html ?? '')
    .replaceAll(' class="form-variable"', '')
    .replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (m, key) => props.previewValues[language.value][key] ?? m)

const save = () => form.put(`/templates/${props.template.id}`)
</script>

<template>
    <!-- Page setup inputs (paper_size, orientation, margin_*, font_*) bind to form.* -->
    <button v-for="l in (['en', 'id'] as const)" :key="l" @click="language = l">{{ l.toUpperCase() }}</button>

    <!-- Both languages stay mounted (v-show) so each keeps its undo history. -->
    <DocumentEditor
        v-for="l in (['en', 'id'] as const)" v-show="language === l" :key="l"
        v-model:header="form[`header_${l}`]"
        v-model:content="form[`content_${l}`]"
        v-model:footer="form[`footer_${l}`]"
        :page="page" :variables="variables" :font-options="fontOptions" :errors="errors[l]"
    />

    <button @click="showPreview = true">Preview</button>
    <button :disabled="form.processing" @click="save">Save</button>

    <FormDocument
        v-if="showPreview" :page="page"
        :content="fill(form[`content_${language}`])"
        :header="fill(form[`header_${language}`])"
        :footer="fill(form[`footer_${language}`])"
    />
</template>
```

Without Inertia, use a plain `reactive({...})` for the form and
`fetch`/axios for saving. The editor components don't depend on Inertia.

### 5.5 Rendering a document (screen and PDF)

```php
// Values come from FormVariableService::resolve() (+ 'signed_name' once signed).
$render = fn (string $html) => FormDeclarationService::keepBlankLines(
    $variables->render($html, $values, $locale)
);

$document = [
    'title'   => $template->title($locale),
    'content' => $render($template->content($locale)),
    'header'  => $render($template->header($locale)),
    'footer'  => $render($template->footer($locale)),
];
```

- **Screen:** pass `content`, `header`, `footer` and `$template->pageLayout()`
  to `FormDocument.vue`.
- **PDF:** run each through `FormImageService::inlineForPdf()`, then load
  `pdf.form_declaration` and call `setPaper([0, 0, mm→pt(width), mm→pt(height)])`
  as `FormPdfService` does.

`keepBlankLines()` is a one-line static helper. Copy it out of
`FormDeclarationService` if you don't take that class over.

---

## 6. Checks after porting

- [ ] Type in the body, header and footer. The toolbar follows the zone you
      clicked, and the bold/heading/table states update immediately.
- [ ] Tab inserts a gap in text, indents in a list, and moves cells in a
      table. Esc leaves the editor.
- [ ] Insert a variable. It shows as a chip and deletes as one unit.
- [ ] Upload a PNG. It shows in the editor, in `FormDocument` and in the PDF.
      Uploading an SVG or a file over 2 MB returns an error message.
- [ ] Drag column widths, set a row height, make a table borderless, merge
      cells. The editor, preview and PDF should all match.
- [ ] Type several blank lines. They are kept on screen and in the PDF.
- [ ] Change the paper, orientation and margins. The editor resizes, and the
      PDF has the same geometry. A 3-page document repeats the header and
      footer on every page.
- [ ] Post `<script>`, `onerror=`, `<img src="https://…">` or
      `style="position:fixed"` directly to the save endpoint. None of it is
      stored.
- [ ] Switch the language tab and back. Undo still works in both.
- [ ] Narrow the window below about 600px. `FormDocument` switches to reflow
      mode.

---

## 7. Keep these in sync

These pairs must change together, or the screen and the PDF will drift apart:

| If you change… | …also change |
| --- | --- |
| an extension in `useFormEditor.ts` / `EditorExtensions.ts` | the allow-list in `FormHtmlSanitizer.php` |
| `.form-content` rules in `form-editor.css` | the `.content` rules in `pdf/form_declaration.blade.php` |
| `fonts` / `font_sizes` / colours in `config/forms.php` | nothing else (the editor, sanitizer and validation all read config); a new font also needs registering with dompdf |
| a variable key in `config/forms.php` | `FormVariableService::resolve()` |
| `FormTemplate::pageLayout()` | the `pageLayout` computed in your editor page |
| the image route prefix | `FormImageService::URL_PREFIX` |
