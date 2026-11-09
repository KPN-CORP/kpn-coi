<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Forms (Pakta Integritas and every other form type)
|--------------------------------------------------------------------------
|
| Variables an admin can drop into a template. Each key is written into the
| template body as {{key}} and replaced with the signer's own data when the
| form is shown, signed, and printed -- see FormVariableService for
| where each value is read from. Adding a key here makes it insertable in the
| editor; the resolver must learn to fill it too, or it renders as "-".
|
*/

return [

    /*
    | Accounts allowed to grant/revoke the Master Settings permissions
    | (form_master.*: templates and form types) in Role Management, by email.
    | Comma-separated in .env, e.g.
    |   FORM_MASTER_ADMIN_EMAILS=hc.admin@kpn-corp.com,it.admin@kpn-corp.com
    | Empty = nobody can change them from the screen (existing grants stay).
    | See App\Services\FormMasterAccess.
    */
    'master_admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FORM_MASTER_ADMIN_EMAILS', ''))
    ))),

    /*
    | Accounts allowed to open Import Center and to grant/revoke its
    | permissions (import.*) in Role Management, by email -- same rule as
    | Master Settings above. Comma-separated in .env, e.g.
    |   IMPORT_CENTER_ADMIN_EMAILS=hc.admin@kpn-corp.com,it.admin@kpn-corp.com
    | Empty = Import Center is closed to everyone (existing grants stay).
    | See App\Services\ImportCenterAccess.
    */
    'import_admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IMPORT_CENTER_ADMIN_EMAILS', ''))
    ))),

    'variables' => [
        'employee_name' => ['en' => 'Full Name', 'id' => 'Nama Lengkap'],
        'employee_id' => ['en' => 'Employee ID', 'id' => 'ID Karyawan'],
        'id_number' => ['en' => 'ID Card Number (KTP)', 'id' => 'Nomor KTP'],
        'place_of_birth' => ['en' => 'Place of Birth', 'id' => 'Tempat Lahir'],
        'date_of_birth' => ['en' => 'Date of Birth', 'id' => 'Tanggal Lahir'],
        'address' => ['en' => 'Permanent Address', 'id' => 'Alamat Tetap'],
        'email' => ['en' => 'Email', 'id' => 'Email'],
        'job_title' => ['en' => 'Job Title', 'id' => 'Jabatan'],
        'department' => ['en' => 'Department', 'id' => 'Departemen'],
        'company' => ['en' => 'Company', 'id' => 'Perusahaan'],
        'business_unit' => ['en' => 'Business Unit', 'id' => 'Unit Bisnis'],
        'work_location' => ['en' => 'Work Location', 'id' => 'Lokasi Kerja'],
        'date_of_joining' => ['en' => 'Date of Joining', 'id' => 'Tanggal Bergabung'],
        'period' => ['en' => 'Period', 'id' => 'Periode'],
        'sign_date' => ['en' => 'Signing Date & Time', 'id' => 'Tanggal & Waktu Tanda Tangan'],
        // The name the signer typed, in handwriting (as on the COI PDF). Only
        // the name: labels and the date are the template's own layout.
        'signature' => ['en' => 'Signature', 'id' => 'Tanda Tangan'],
        // Paging: the PDF fills these in on every page (CSS page counters),
        // so put them in the header or footer with your own wording, e.g.
        // "Page {{page_number}} of {{page_count}}".
        'page_number' => ['en' => 'Page Number', 'id' => 'Nomor Halaman'],
        'page_count' => ['en' => 'Total Pages', 'id' => 'Jumlah Halaman'],
    ],

    /*
    | Fonts offered in the editor and as a template's default font. Only
    | families dompdf can actually draw are listed: its bundled DejaVu fonts
    | (full Unicode) and the PDF core fonts it maps Arial/Times/Courier onto.
    | Calibri is the one extra: the PDF draws it with Carlito (bundled in
    | resources/fonts, free and metric-compatible, so lines break the same) --
    | the real Calibri may not be redistributed. Anything else (Georgia, ...)
    | would silently fall back in the PDF and the signed document would no
    | longer match the screen; a new one needs its TTF and @font-face in
    | pdf/form_declaration.blade.php and coi.css. `css` is the
    | exact font-family value stored in the HTML; FormHtmlSanitizer accepts
    | only these.
    */
    'fonts' => [
        'dejavu-sans' => ['label' => 'DejaVu Sans', 'css' => "'DejaVu Sans', Verdana, sans-serif"],
        'arial' => ['label' => 'Arial / Helvetica', 'css' => 'Arial, Helvetica, sans-serif'],
        'times' => ['label' => 'Times New Roman', 'css' => "'Times New Roman', Times, serif"],
        'dejavu-serif' => ['label' => 'DejaVu Serif', 'css' => "'DejaVu Serif', Georgia, serif"],
        'courier' => ['label' => 'Courier New', 'css' => "'Courier New', Courier, monospace"],
        'calibri' => ['label' => 'Calibri', 'css' => 'Calibri, Carlito, sans-serif'],
    ],

    // Point sizes offered for text and as a template's base size.
    'font_sizes' => [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36],

    // Line spacing multipliers for a template's body text.
    'line_heights' => [1.0, 1.15, 1.3, 1.5, 2.0],

    // Text and highlight colours offered in the editor (hex only).
    'text_colors' => ['#000000', '#475569', '#ab2f2b', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#2563eb', '#7c3aed'],
    'highlight_colors' => ['#fef08a', '#bbf7d0', '#bfdbfe', '#fecaca', '#e9d5ff', '#e5e7eb'],

];
