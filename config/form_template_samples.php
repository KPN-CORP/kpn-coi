<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Form template samples
|--------------------------------------------------------------------------
|
| `form_type` is the code of the form type a sample belongs to; the editor
| offers a sample only for that type, and the seeder files it under it. A
| sample without one is offered for every type.
|
| The template name (title_*) is not printed, so each sample opens its body
| with the document heading itself.
|
| Optional per sample: header_en/id, footer_en/id, paper_size, orientation and
| margin_* -- when present they replace the editor's page layout too.
|
| Starting points offered in the template editor ("Use sample") and by
| FormTemplateSampleSeeder. They are only ever copied into a new template --
| editing one here never changes a template that already exists. Placeholders
| use the editor's own variable markup so they load as chips; every key must
| exist in config/forms.php.
|
*/

$v = fn (string $key): string => '<span data-variable="'.$key.'">{{'.$key.'}}</span>';

$identityEn = '<table><tbody>'
    .'<tr><td><p>Full Name</p></td><td><p>'.$v('employee_name').'</p></td></tr>'
    .'<tr><td><p>Employee ID</p></td><td><p>'.$v('employee_id').'</p></td></tr>'
    .'<tr><td><p>ID Card Number (KTP)</p></td><td><p>'.$v('id_number').'</p></td></tr>'
    .'<tr><td><p>Job Title</p></td><td><p>'.$v('job_title').'</p></td></tr>'
    .'<tr><td><p>Department</p></td><td><p>'.$v('department').'</p></td></tr>'
    .'<tr><td><p>Company / Business Unit</p></td><td><p>'.$v('company').' / '.$v('business_unit').'</p></td></tr>'
    .'<tr><td><p>Address</p></td><td><p>'.$v('address').'</p></td></tr>'
    .'</tbody></table>';

$identityId = '<table><tbody>'
    .'<tr><td><p>Nama Lengkap</p></td><td><p>'.$v('employee_name').'</p></td></tr>'
    .'<tr><td><p>ID Karyawan</p></td><td><p>'.$v('employee_id').'</p></td></tr>'
    .'<tr><td><p>Nomor KTP</p></td><td><p>'.$v('id_number').'</p></td></tr>'
    .'<tr><td><p>Jabatan</p></td><td><p>'.$v('job_title').'</p></td></tr>'
    .'<tr><td><p>Departemen</p></td><td><p>'.$v('department').'</p></td></tr>'
    .'<tr><td><p>Perusahaan / Unit Bisnis</p></td><td><p>'.$v('company').' / '.$v('business_unit').'</p></td></tr>'
    .'<tr><td><p>Alamat</p></td><td><p>'.$v('address').'</p></td></tr>'
    .'</tbody></table>';

return [

    // HC form 017.FORM.CHC.X.2026, transcribed from the Word original: A4,
    // logo header, form number footer. The "Yang menyatakan / Nama / Tanggal"
    // block of the Word form is left out -- the PDF prints the electronic
    // signature block itself.
    [
        'key' => 'kpn_2026',
        'form_type' => 'integrity-pact',
        'name' => ['en' => 'KPN Corporation Integrity Pact 2026 (017.FORM.CHC.X.2026)', 'id' => 'Pakta Integritas KPN Corporation 2026 (017.FORM.CHC.X.2026)'],
        'title_en' => 'Integrity Pact',
        'title_id' => 'Pakta Integritas',
        'header_en' => '<p><img src="/forms/images/sample-kpncorp-logo.jpg" width="218" alt="KPN Corp"></p>',
        'header_id' => '<p><img src="/forms/images/sample-kpncorp-logo.jpg" width="218" alt="KPN Corp"></p>',
        'footer_en' => '<p>Form Number: 017.FORM.CHC.X.2026</p>',
        'footer_id' => '<p>Nomor Form: 017.FORM.CHC.X.2026</p>',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'margin_top' => 27,
        'margin_right' => 12.7,
        'margin_bottom' => 18,
        'margin_left' => 12.7,
        // Calibri in the Word original; Arial is the closest font the PDF has.
        'font_family' => 'arial',
        'font_size' => 10,
        'line_height' => 1.3,
        'content_id' => '<h1 style="text-align: center">PAKTA INTEGRITAS</h1>'
            .'<p style="text-align: justify">Saya yang bertandatangan di bawah ini:</p>'
            .'<table><tbody>'
            .'<tr><td><p>Nama</p></td><td><p>'.$v('employee_name').'</p></td></tr>'
            .'<tr><td><p>No. KTP</p></td><td><p>'.$v('id_number').'</p></td></tr>'
            .'<tr><td><p>Tempat/Tanggal Lahir</p></td><td><p>'.$v('place_of_birth').' / '.$v('date_of_birth').'</p></td></tr>'
            .'<tr><td><p>Alamat</p></td><td><p>'.$v('address').'</p></td></tr>'
            .'</tbody></table>'
            .'<p style="text-align: justify">dengan &ldquo;Pakta Integritas&rdquo; ini menyatakan bahwa selama bekerja dalam lingkungan perusahaan sebagai karyawan '.$v('company').' yang merupakan bagian dari KPN Corporation, saya akan:</p>'
            .'<ol>'
            .'<li><p style="text-align: justify">Tunduk dan mematuhi semua aturan, etik bisnis yang berlaku di lingkungan '.$v('company').' serta standar aturan, etika lainnya yang berlaku di lingkungan afiliasinya dalam KPN Corporation.</p></li>'
            .'<li><p style="text-align: justify">Bekerja dengan sungguh-sungguh dengan memahami kewajiban dan berperilaku sesuai nilai-nilai inti KPN Corporation untuk dapat memberikan kontribusi yang positif dan produktif terhadap tujuan Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Tidak meminta atau menerima pemberian dalam bentuk apapun sehubungan dengan tugas pekerjaan saya, baik dari pihak luar maupun dari pihak internal Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Selalu menaati hukum dan perundang-undangan yang berlaku di Indonesia, diantaranya:</p>'
            .'<ol>'
            .'<li><p style="text-align: justify">Tidak menggunakan, mengedarkan, dan/atau menyalahgunakan bahan narkotika, zat adiktif, minuman keras dan zat-zat terlarang lainnya di dalam area lingkungan kerja Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Tidak terlibat atau melakukan segala bentuk perjudian, pertengkaran atau perkelahian di dalam area lingkungan kerja Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Tidak melakukan tindakan asusila dan pelecehan seksual di dalam area lingkungan kerja Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Tidak melakukan tindakan pencurian, penggelapan, penipuan, korupsi, atau kecurangan di dalam area lingkungan kerja Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Tidak melakukan tindakan pemalsuan informasi baik dalam bentuk tertulis maupun lisan selama bekerja di Perusahaan, serta;</p></li>'
            .'<li><p style="text-align: justify">Tidak melakukan tindakan-tindakan dan/atau perilaku-perilaku pelanggaran hukum lainnya yang dapat mengakibatkan kerugian, tanggung jawab hukum, tuntutan, klaim, sanksi, dan/atau mempengaruhi reputasi dan citra Perusahaan.</p></li>'
            .'</ol></li>'
            .'<li><p style="text-align: justify">Tidak membocorkan data, dokumen atau informasi-informasi resmi lainnya yang digolongkan oleh Perusahaan sebagai rahasia Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Menghindari dan/atau melaporkan ke pihak yang berwenang dalam Perusahaan segala bentuk konflik kepentingan (<em>conflict of interest</em>) yang terjadi dalam Perusahaan;</p></li>'
            .'<li><p style="text-align: justify">Tidak menggunakan aset dan sumber daya milik Perusahaan untuk kepentingan pribadi; serta mengembalikan seluruh aset dan sumber daya Perusahaan yang digunakan paling lambat di hari terakhir bekerja saat hubungan kerja dengan Perusahaan berakhir.</p></li>'
            .'</ol>'
            .'<p style="text-align: justify">Demikian &ldquo;Pakta Integritas&rdquo; ini saya buat dan tanda tangani tanpa ada paksaan dari pihak manapun. Apabila di kemudian hari terbukti perilaku saya bertentangan dengan pernyataan-pernyataan yang saya buat dalam &ldquo;Pakta Integritas&rdquo; ini maka saya bersedia menerima konsekuensi sesuai peraturan, kebijakan dan ketentuan yang berlaku dalam Perusahaan.</p>',
        'content_en' => '<h1 style="text-align: center">INTEGRITY PACT</h1>'
            .'<p style="text-align: justify">I, the undersigned:</p>'
            .'<table><tbody>'
            .'<tr><td><p>Name</p></td><td><p>'.$v('employee_name').'</p></td></tr>'
            .'<tr><td><p>ID Number</p></td><td><p>'.$v('id_number').'</p></td></tr>'
            .'<tr><td><p>Place/Date of Birth</p></td><td><p>'.$v('place_of_birth').' / '.$v('date_of_birth').'</p></td></tr>'
            .'<tr><td><p>Address</p></td><td><p>'.$v('address').'</p></td></tr>'
            .'</tbody></table>'
            .'<p style="text-align: justify">through this &ldquo;Integrity Pact&rdquo; hereby declare that throughout my employment with '.$v('company').', a member of KPN Corporation, I shall:</p>'
            .'<ol>'
            .'<li><p style="text-align: justify">Be bound by and fully comply with all applicable regulation and business ethics of '.$v('company').', as well as other policies and ethical standards applicable to its affiliated companies within KPN Corporation;</p></li>'
            .'<li><p style="text-align: justify">Work diligently by understanding my obligations and behaving in accordance with KPN Corporation&rsquo;s core values to contribute positively and productively to the Company&rsquo;s objective;</p></li>'
            .'<li><p style="text-align: justify">Not solicit or accept any form of gratuity related to my job duties, either from external parties or from internal parties of the Company;</p></li>'
            .'<li><p style="text-align: justify">Always comply with the laws and regulations applicable in Indonesia, including:</p>'
            .'<ol>'
            .'<li><p style="text-align: justify">Not use, distribute, and/or abuse narcotics, addictive substances, alcoholic beverages, and other prohibited substances within the Company&rsquo;s work environment;</p></li>'
            .'<li><p style="text-align: justify">Not engage in or commit any form of gambling, quarrels, or fights within the Company&rsquo;s work environment;</p></li>'
            .'<li><p style="text-align: justify">Not commit immoral acts and sexual harassment within the Company&rsquo;s work environment;</p></li>'
            .'<li><p style="text-align: justify">Not commit acts of theft, embezzlement, fraud, corruption, or deceit within the Company&rsquo;s work environment;</p></li>'
            .'<li><p style="text-align: justify">Not commit acts of falsification of information, whether in written or verbal form, while working at the Company;</p></li>'
            .'<li><p style="text-align: justify">Not to engage in any act or conduct that violates applicable laws and regulations and that may result in any loss, legal liability, demand, claim, sanction, and/or reputational damage to KPN Corporation.</p></li>'
            .'</ol></li>'
            .'<li><p style="text-align: justify">Not disclose data, documents, or other official information classified by the Company as Company secrets;</p></li>'
            .'<li><p style="text-align: justify">Avoid and/or report to the authorized parties within the Company any form of conflict of interest that occurs within the Company;</p></li>'
            .'<li><p style="text-align: justify">Not use Company assets and resources for personal gain; and return all Company assets and resources used no later than the last day of work when the employment relationship with the Company ends.</p></li>'
            .'</ol>'
            .'<p style="text-align: justify">This &ldquo;Integrity Pact&rdquo; is made and signed by me without any form of coercion from any party. If in the future my behavior is proven to contradict the statements I made in this &ldquo;Integrity Pact&rdquo;, then I am willing to accept the consequences in accordance with the regulations, policies, and provisions applied in the Company.</p>',
    ],

    [
        'key' => 'employee_general',
        'form_type' => 'integrity-pact',
        'name' => ['en' => 'Employee Integrity Pact (general)', 'id' => 'Pakta Integritas Karyawan (umum)'],
        'title_en' => 'Employee Integrity Pact',
        'title_id' => 'Pakta Integritas Karyawan',
        'content_en' => '<h1 style="text-align: center">EMPLOYEE INTEGRITY PACT</h1>'
            .'<p>I, the undersigned:</p>'.$identityEn
            .'<p>hereby declare for the period '.$v('period').' that I will:</p>'
            .'<ol>'
            .'<li><p>Perform my duties honestly, transparently, objectively and accountably, in line with the Company\'s Code of Conduct and the prevailing laws and regulations.</p></li>'
            .'<li><p>Not offer, give, request or accept bribes, gratuities, commissions or any other form of benefit, directly or indirectly, in connection with my position or duties.</p></li>'
            .'<li><p>Avoid conflicts of interest, and promptly disclose any actual or potential conflict of interest to my superior and through the Conflict of Interest Declaration.</p></li>'
            .'<li><p>Keep the Company\'s confidential information secure and not use it for personal gain or for the benefit of any third party.</p></li>'
            .'<li><p>Use Company assets and facilities only for legitimate business purposes.</p></li>'
            .'<li><p>Report any violation of this integrity pact that I become aware of through the Company\'s whistleblowing channel.</p></li>'
            .'</ol>'
            .'<p>I understand that any violation of this integrity pact may result in sanctions in accordance with Company regulations and the applicable laws.</p>'
            .'<p>This integrity pact is made consciously and without coercion from any party.</p>'
            .'<p>'.$v('work_location').', '.$v('sign_date').'</p>',
        'content_id' => '<h1 style="text-align: center">PAKTA INTEGRITAS KARYAWAN</h1>'
            .'<p>Saya yang bertanda tangan di bawah ini:</p>'.$identityId
            .'<p>dengan ini menyatakan untuk periode '.$v('period').' bahwa saya akan:</p>'
            .'<ol>'
            .'<li><p>Melaksanakan tugas secara jujur, transparan, objektif, dan akuntabel sesuai dengan Kode Etik Perusahaan serta peraturan perundang-undangan yang berlaku.</p></li>'
            .'<li><p>Tidak menawarkan, memberikan, meminta, atau menerima suap, gratifikasi, komisi, maupun manfaat dalam bentuk apa pun, baik secara langsung maupun tidak langsung, yang berkaitan dengan jabatan atau tugas saya.</p></li>'
            .'<li><p>Menghindari benturan kepentingan, serta segera mengungkapkan setiap benturan kepentingan yang aktual maupun potensial kepada atasan dan melalui Deklarasi Konflik Kepentingan.</p></li>'
            .'<li><p>Menjaga kerahasiaan informasi Perusahaan dan tidak menggunakannya untuk kepentingan pribadi atau pihak ketiga mana pun.</p></li>'
            .'<li><p>Menggunakan aset dan fasilitas Perusahaan hanya untuk kepentingan bisnis yang sah.</p></li>'
            .'<li><p>Melaporkan setiap pelanggaran atas pakta integritas ini yang saya ketahui melalui saluran whistleblowing Perusahaan.</p></li>'
            .'</ol>'
            .'<p>Saya memahami bahwa setiap pelanggaran atas pakta integritas ini dapat dikenakan sanksi sesuai dengan peraturan Perusahaan dan hukum yang berlaku.</p>'
            .'<p>Demikian pakta integritas ini saya buat dengan penuh kesadaran dan tanpa paksaan dari pihak mana pun.</p>'
            .'<p>'.$v('work_location').', '.$v('sign_date').'</p>',
    ],

    [
        'key' => 'anti_bribery',
        'form_type' => 'integrity-pact',
        'name' => ['en' => 'Anti-Bribery & Anti-Gratification Statement', 'id' => 'Pernyataan Anti Suap & Anti Gratifikasi'],
        'title_en' => 'Anti-Bribery and Anti-Gratification Statement',
        'title_id' => 'Pernyataan Anti Suap dan Anti Gratifikasi',
        'content_en' => '<h1 style="text-align: center">ANTI-BRIBERY AND ANTI-GRATIFICATION STATEMENT</h1>'
            .'<p>I, '.$v('employee_name').' (Employee ID '.$v('employee_id').'), '.$v('job_title').' at '.$v('company').', hereby state that:</p>'
            .'<ol>'
            .'<li><p>I have read and understood the Company\'s Anti-Bribery and Anti-Gratification Policy.</p></li>'
            .'<li><p>I will not give or receive any gift, entertainment, hospitality, money or other benefit that could influence, or appear to influence, a business decision.</p></li>'
            .'<li><p>Any gift or benefit I receive in connection with my work will be reported to the Company within 7 (seven) working days, and returned or handed over where required by the policy.</p></li>'
            .'<li><p>I will not use intermediaries, family members or third parties to do anything I am prohibited from doing myself.</p></li>'
            .'</ol>'
            .'<p>I am prepared to accept the consequences under Company regulations and the law should I breach this statement.</p>'
            .'<p>'.$v('work_location').', '.$v('sign_date').'</p>',
        'content_id' => '<h1 style="text-align: center">PERNYATAAN ANTI SUAP DAN ANTI GRATIFIKASI</h1>'
            .'<p>Saya, '.$v('employee_name').' (ID Karyawan '.$v('employee_id').'), '.$v('job_title').' di '.$v('company').', dengan ini menyatakan bahwa:</p>'
            .'<ol>'
            .'<li><p>Saya telah membaca dan memahami Kebijakan Anti Suap dan Anti Gratifikasi Perusahaan.</p></li>'
            .'<li><p>Saya tidak akan memberikan atau menerima hadiah, jamuan, fasilitas, uang, atau manfaat lain yang dapat memengaruhi, atau terlihat memengaruhi, suatu keputusan bisnis.</p></li>'
            .'<li><p>Setiap hadiah atau manfaat yang saya terima terkait pekerjaan akan saya laporkan kepada Perusahaan paling lambat 7 (tujuh) hari kerja, serta dikembalikan atau diserahkan apabila diwajibkan oleh kebijakan.</p></li>'
            .'<li><p>Saya tidak akan menggunakan perantara, anggota keluarga, atau pihak ketiga untuk melakukan hal-hal yang dilarang untuk saya lakukan sendiri.</p></li>'
            .'</ol>'
            .'<p>Saya bersedia menerima konsekuensi sesuai peraturan Perusahaan dan hukum yang berlaku apabila melanggar pernyataan ini.</p>'
            .'<p>'.$v('work_location').', '.$v('sign_date').'</p>',
    ],

    [
        'key' => 'non_employee',
        'form_type' => 'integrity-pact',
        'name' => ['en' => 'Integrity Pact for Non-Employees / Contractors', 'id' => 'Pakta Integritas Non-Karyawan / Kontraktor'],
        'title_en' => 'Integrity Pact for Non-Employees',
        'title_id' => 'Pakta Integritas Non-Karyawan',
        'content_en' => '<h1 style="text-align: center">INTEGRITY PACT FOR NON-EMPLOYEES</h1>'
            .'<p>I, the undersigned:</p>'
            .'<table><tbody>'
            .'<tr><td><p>Full Name</p></td><td><p>'.$v('employee_name').'</p></td></tr>'
            .'<tr><td><p>ID Card Number (KTP)</p></td><td><p>'.$v('id_number').'</p></td></tr>'
            .'<tr><td><p>Email</p></td><td><p>'.$v('email').'</p></td></tr>'
            .'<tr><td><p>Assigned Business Unit</p></td><td><p>'.$v('business_unit').'</p></td></tr>'
            .'<tr><td><p>Work Location</p></td><td><p>'.$v('work_location').'</p></td></tr>'
            .'</tbody></table>'
            .'<p>as a party working with the Company during the period '.$v('period').', declare that I will:</p>'
            .'<ul>'
            .'<li><p>Comply with the Company\'s Code of Conduct and the policies applicable to me while working for or with the Company.</p></li>'
            .'<li><p>Not give or receive bribes, gratuities or other improper benefits to or from Company employees or any other party.</p></li>'
            .'<li><p>Disclose any relationship with Company employees or business partners that could create a conflict of interest.</p></li>'
            .'<li><p>Protect all Company information and data entrusted to me, during and after my engagement.</p></li>'
            .'</ul>'
            .'<p>I understand that a breach of this integrity pact may lead to termination of my engagement and other consequences under the agreement and the law.</p>'
            .'<p>'.$v('sign_date').'</p>',
        'content_id' => '<h1 style="text-align: center">PAKTA INTEGRITAS NON-KARYAWAN</h1>'
            .'<p>Saya yang bertanda tangan di bawah ini:</p>'
            .'<table><tbody>'
            .'<tr><td><p>Nama Lengkap</p></td><td><p>'.$v('employee_name').'</p></td></tr>'
            .'<tr><td><p>Nomor KTP</p></td><td><p>'.$v('id_number').'</p></td></tr>'
            .'<tr><td><p>Email</p></td><td><p>'.$v('email').'</p></td></tr>'
            .'<tr><td><p>Unit Bisnis Penugasan</p></td><td><p>'.$v('business_unit').'</p></td></tr>'
            .'<tr><td><p>Lokasi Kerja</p></td><td><p>'.$v('work_location').'</p></td></tr>'
            .'</tbody></table>'
            .'<p>sebagai pihak yang bekerja sama dengan Perusahaan selama periode '.$v('period').', menyatakan bahwa saya akan:</p>'
            .'<ul>'
            .'<li><p>Mematuhi Kode Etik Perusahaan dan kebijakan yang berlaku bagi saya selama bekerja untuk atau bersama Perusahaan.</p></li>'
            .'<li><p>Tidak memberikan atau menerima suap, gratifikasi, atau manfaat tidak wajar lainnya kepada atau dari karyawan Perusahaan maupun pihak lain.</p></li>'
            .'<li><p>Mengungkapkan setiap hubungan dengan karyawan atau mitra bisnis Perusahaan yang dapat menimbulkan benturan kepentingan.</p></li>'
            .'<li><p>Menjaga seluruh informasi dan data Perusahaan yang dipercayakan kepada saya, baik selama maupun setelah masa kerja sama.</p></li>'
            .'</ul>'
            .'<p>Saya memahami bahwa pelanggaran atas pakta integritas ini dapat mengakibatkan pengakhiran kerja sama dan konsekuensi lain sesuai perjanjian dan hukum yang berlaku.</p>'
            .'<p>'.$v('sign_date').'</p>',
    ],

];
