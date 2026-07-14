<?php

return [

    'name' => env(
        'COI_APP_NAME',
        'Conflict of Interest Declaration System',
    ),

    'url' => env(
        'COI_APP_URL',
        env('APP_URL')
    ),

    'support_email' => env(
        'COI_SUPPORT_EMAIL',
        env('MAIL_FROM_ADDRESS')
    ),


    'questions' => [

        [
            'key' => 'business_affiliation',

            'appendix' => 1,

            'title' => [
                'en' => 'I, my Sibling(s), and/or my Immediate Family² have ownership (whether direct or indirect, including
                        through a nominee arrangement and/or representation) of a business, ventures, and/or affiliation with
                        a company, organizations, suppliers, customers, competitors, or other entities that have the same
                        type of business as companies under the KPN Corporation group.',
                'id' => 'Saya, Saudara Kandung, dan/atau Keluarga Inti Saya² memiliki kepemilikan (baik langsung   
                        ataupun tidak, dengan skema “nominee” dan/atau perwakilan) bisnis, usaha, dan/atau   
                        afiliasi dengan perusahaan, organisasi, pemasok, konsumen, kompetitor, atau entitas lain   
                        yang memiliki jenis bisnis yang sama dengan perusahaan yang bernaung dalam grup KPN   
                        Corporation.',
            ],

            'appendix_title' => [
                'id' => 'LAMPIRAN 1 - DEKLARASI KONFLIK KEPENTINGAN',
                'en' => 'APPENDIX 1 - CONFLICT OF INTEREST DECLARATION',
            ],

            'appendix_subtitle' => [
                'id' => 'Tambahan Potensi Konflik Kepentingan 1',
                'en' => 'Additional Potential Conflict of Interest 1',
            ],

            'fields' => [

                [
                    'key' => 'entity_name',

                    'label' => [
                        'id' => 'Nama Entitas',
                        'en' => 'Name of Entity',
                    ],
                ],

                [
                    'key' => 'ownership_year',

                    'label' => [
                        'id' => 'Tahun Kepemilikan (Jika Milik Pribadi)',
                        'en' => 'Year of Ownership (If Personally Owned)',
                    ],
                    'type' => 'year',
                ],

                [
                    'key' => 'entity_owner',

                    'label' => [
                        'id' => 'Pemilik Entitas (Nama Lengkap)',
                        'en' => 'Entity Owner (Full Name)',
                    ],
                ],

                [
                    'key' => 'relationship',

                    'label' => [
                        'id' => 'Hubungan dengan Pemilik Entitas',
                        'en' => 'Relationship to the Entity Owner',
                    ],

                    'type' => 'select',

                    'options' => [

                        [
                            'value' => 'husband_wife',
                            'label' => [
                                'en' => 'Husband/Wife',
                                'id' => 'Suami/Istri',
                            ],
                        ],

                        [
                            'value' => 'father',
                            'label' => [
                                'en' => 'Father',
                                'id' => 'Ayah',
                            ],
                        ],

                        [
                            'value' => 'mother',
                            'label' => [
                                'en' => 'Mother',
                                'id' => 'Ibu',
                            ],
                        ],

                        [
                            'value' => 'son',
                            'label' => [
                                'en' => 'Son',
                                'id' => 'Anak Laki-laki',
                            ],
                        ],

                        [
                            'value' => 'daughter',
                            'label' => [
                                'en' => 'Daughter',
                                'id' => 'Anak Perempuan',
                            ],
                        ],

                        [
                            'value' => 'brother',
                            'label' => [
                                'en' => 'Brother',
                                'id' => 'Saudara Laki-laki',
                            ],
                        ],

                        [
                            'value' => 'sister',
                            'label' => [
                                'en' => 'Sister',
                                'id' => 'Saudara Perempuan',
                            ],
                        ],

                        [
                            'value' => 'others',
                            'label' => [
                                'en' => 'Others (please specify)',
                                'id' => 'Lainnya (sebutkan)',
                            ],

                            'requires' => [
                                [
                                    'key' => 'others',
                                    'type' => 'text',
                                    'label' => [
                                        'en' => 'Please specify',
                                        'id' => 'Mohon sebutkan',
                                    ],
                                ],
                            ],
                        ],

                    ],
                ],

            ],

        ],
        [
            'key' => 'professional_relationship',

            'appendix' => 2,

            'title' => [
                'en' => 'I, my sibling(s), and/or my Immediate Family² have a professional relationship or are stakeholders, including but not limited to as Commissioner, Director, or any other positions in a company, organization, supplier, consumer, competitor, or other entity that has a business relationship with companies under the KPN Corporation group.',
                'id' => 'Saya, Saudara Kandung, dan/atau Keluarga Inti Saya² memiliki hubungan profesional atau   
                    menjadi pemangku kepentingan termasuk namun tidak terbatas sebagai Komisaris, Direksi,   
                    atau jabatan lainnya di perusahaan, organisasi, pemasok, konsumen, kompetitor, atau   
                    entitas lain yang memiliki hubungan bisnis dengan perusahaan yang bernaung dalam grup   
                    KPN Corporation.',
            ],

            'appendix_title' => [
                'id' => 'LAMPIRAN 2 - DEKLARASI KONFLIK KEPENTINGAN',
                'en' => 'APPENDIX 2 - CONFLICT OF INTEREST DECLARATION',
            ],

            'appendix_subtitle' => [
                'id' => 'Tambahan Potensi Konflik Kepentingan 2',
                'en' => 'Additional Potential Conflict of Interest 2',
            ],

            'fields' => [

                [
                    'key' => 'entity_name',
                    'label' => [
                        'id' => 'Nama Entitas',
                        'en' => 'Name of Entity',
                    ],
                ],

                [
                    'key' => 'relationship',
                    'label' => [
                        'id' => 'Jenis Hubungan Profesional',
                        'en' => 'Professional Relationship',
                    ],

                    'type' => 'select',

                    'options' => [

                        [
                            'value' => 'supplier',
                            'label' => [
                                'en' => 'Supplier',
                                'id' => 'Pemasok',
                            ],
                        ],

                        [
                            'value' => 'customer',
                            'label' => [
                                'en' => 'Customer',
                                'id' => 'Pelanggan',
                            ],
                        ],

                        [
                            'value' => 'competitor',
                            'label' => [
                                'en' => 'Competitor',
                                'id' => 'Kompetitor',
                            ],
                        ],

                        [
                            'value' => 'others',
                            'label' => [
                                'en' => 'Others (please specify)',
                                'id' => 'Lainnya (sebutkan)',
                            ],

                            'requires' => [
                                [
                                    'key' => 'others',
                                    'type' => 'text',
                                    'label' => [
                                        'en' => 'Please specify',
                                        'id' => 'Mohon sebutkan',
                                    ],
                                ],
                            ],
                        ],

                    ],
                ],

                [
                    'key' => 'employment_period',
                    'label' => [
                        'id' => 'Masa Kerja Dalam Entitas',
                        'en' => 'Employment Period in the Entity',
                    ],
                    'type' => 'date_range',
                ],

                [
                    'key' => 'business_unit',
                    'type' => 'select',
                    'label' => [
                        'id' => 'Bisnis Unit Dalam Grup KPN Corporation yang Memiliki Hubungan Bisnis',
                        'en' => 'Business Units under the KPN Corporation Group Engaged in a Business Relationship',
                    ],
                ],
                [
                    'key' => 'company',
                    'type' => 'select',
                    'label' => [
                        'id' => 'Perusahaan/PT Dalam Grup KPN Corporation yang Memiliki Hubungan Bisnis',
                        'en' => 'Company (Legal Entity) under the KPN Corporation Group Engaged in a Business Relationship',
                    ],
                ],
                [
                    'key' => 'department',
                    'type' => 'select',
                    'label' => [
                        'id' => 'Divisi/Departemen Dalam Grup KPN Corporation yang Memiliki Hubungan Bisnis',
                        'en' => 'Divisions/ Departments under the KPN Corporation Group Engaged in a Business Relationship',
                    ],
                ],

            ],

        ],
        [
            'key' => 'equity_ownership',

            'appendix' => 3,

            'title' => [
                'en' => 'I, my Sibling(s), and/or my Immediate Family² have share ownership or other equity interest that could potentially create a conflict of interest or affect objectivity in carrying out my duties while working in companies under the KPN Corporation group, and/or I have an investment in a particular company/business entity except for having stocks and equities in publicly listed companies.',
                'id' => 'Saya, Saudara Kandung, dan/atau Keluarga Inti Saya² memiliki kepemilikan saham atau   
                kepentingan ekuitas lainnya yang berpotensi menimbulkan konflik kepentingan atau   
                mempengaruhi objektivitas dalam melaksanakan tugas selama bekerja dalam perusahaan   
                yang bernaung dalam grup KPN Corporation dan/atau saya memiliki investasi pada   
                perusahaan/badan usaha tertentu kecuali kepemilikan saham pada perusahaan-
                perusahaan yang secara terbuka memperdagangkan saham dan ekuitasnya.',
            ],

            'appendix_title' => [
                'id' => 'LAMPIRAN 3 - DEKLARASI KONFLIK KEPENTINGAN',
                'en' => 'APPENDIX 3 - CONFLICT OF INTEREST DECLARATION',
            ],

            'appendix_subtitle' => [
                'id' => 'Tambahan Potensi Konflik Kepentingan 3',
                'en' => 'Additional Potential Conflict of Interest 3',
            ],

            'fields' => [

                [
                    'key' => 'ownership_type',
                    'label' => [
                        'id' => 'Jenis Kepemilikan',
                        'en' => 'Type of Ownership',
                    ],
                    'type' => 'select',

                    'options' => [

                        [
                            'value' => 'shares',
                            'label' => [
                                'en' => 'Shares',
                                'id' => 'Saham',
                            ],
                        ],

                        [
                            'value' => 'property',
                            'label' => [
                                'en' => 'Property',
                                'id' => 'Properti',
                            ],
                        ],

                        [
                            'value' => 'equity_instruments',
                            'label' => [
                                'en' => 'Equity Instruments',
                                'id' => 'Instrumen Ekuitas',
                            ],
                        ],

                        [
                            'value' => 'others',
                            'label' => [
                                'en' => 'Others (please specify)',
                                'id' => 'Lainnya (sebutkan)',
                            ],

                            'requires' => [
                                [
                                    'key' => 'other',
                                    'type' => 'text',
                                    'label' => [
                                        'en' => 'Please specify',
                                        'id' => 'Mohon sebutkan',
                                    ],
                                ],
                            ],
                        ],

                    ],
                ],

                [
                    'key' => 'entity_name',
                    'label' => [
                        'id' => 'Nama Ekuitas',
                        'en' => 'Name of Equity',
                    ],
                ],

                [
                    'key' => 'ownership_percentage',
                    'label' => [
                        'id' => 'Jumlah Kepemilikan (Dalam %)',
                        'en' => 'Percentage of Ownership (%)',
                    ],
                ],

            ],

        ],
        [
            'key' => 'gifts_benefits',

            'appendix' => 4,

            'title' => [
                'en' => 'I, my Sibling(s), and/or my Immediate Family² receive loans, gifts/other benefits regularly and periodically from parties who have business relationships with companies under the KPN Corporation group.',
                'id' => 'Saya, Saudara Kandung, dan/atau Keluarga Inti Saya² menerima pinjaman, hadiah/manfaat   
                lain secara rutin dan berkala dari pihak-pihak yang memiliki hubungan bisnis dengan   
                perusahaan yang bernaung dalam grup KPN Corporation.',
            ],

            'appendix_title' => [
                'id' => 'LAMPIRAN 4 - DEKLARASI KONFLIK KEPENTINGAN',
                'en' => 'APPENDIX 4 - CONFLICT OF INTEREST DECLARATION',
            ],

            'appendix_subtitle' => [
                'id' => 'Tambahan Potensi Konflik Kepentingan 4',
                'en' => 'Additional Potential Conflict of Interest 4',
            ],

            'fields' => [

                [
                    'key' => 'giver_name',
                    'label' => [
                        'id' => 'Nama Pihak Pemberi',
                        'en' => 'Name of the Giving Party',
                    ],
                ],

                [
                    'key' => 'goods_services',
                    'label' => [
                        'id' => 'Jenis Barang/Jasa yang Diterima',
                        'en' => 'Type of Goods / Services Received',
                    ],
                ],

                [
                    'key' => 'amount_value',
                    'label' => [
                        'id' => 'Jumlah/Nominal Barang/Jasa Yang Diterima',
                        'en' => 'Value or Amount Goods/Services Received',
                    ],
                ],

            ],

        ],
        [
            'key' => 'family_relationship',

            'appendix' => 5,

            'title' => [
                'en' => 'I have family relationships up to 2 (two) levels of family generations³ with other Employees working in companies under the KPN Corporation group.',
                'id' => 'Saya memiliki hubungan keluarga sampai dengan 2 (dua) tingkat generasi keluarga³  
                dengan Karyawan lain yang bekerja di perusahaan yang bernaung dalam grup KPN   
                Corporation.',
            ],

            'appendix_title' => [
                'id' => 'LAMPIRAN 5 - DEKLARASI KONFLIK KEPENTINGAN',
                'en' => 'APPENDIX 5 - CONFLICT OF INTEREST DECLARATION',
            ],

            'appendix_subtitle' => [
                'id' => 'Tambahan Potensi Konflik Kepentingan 5',
                'en' => 'Additional Potential Conflict of Interest 5',
            ],

            'fields' => [

                [
                    'key' => 'family_name',
                    'label' => [
                        'id' => 'Nama Keluarga',
                        'en' => 'Name of Family Member',
                    ],
                ],

                [
                    'key' => 'relationship',
                    'label' => [
                        'id' => 'Hubungan Keluarga',
                        'en' => 'Family Relationship',
                    ],
                    'type' => 'select',

                    'options' => [


                        [
                            'value' => 'uncle',
                            'label' => [
                                'en' => 'Uncle',
                                'id' => 'Paman',
                            ],
                        ],

                        [
                            'value' => 'aunt',
                            'label' => [
                                'en' => 'Aunt',
                                'id' => 'Bibi',
                            ],
                        ],

                        [
                            'value' => 'cousin',
                            'label' => [
                                'en' => 'Cousin',
                                'id' => 'Sepupu',
                            ],
                        ],

                        [
                            'value' => 'nephew',
                            'label' => [
                                'en' => 'Nephew',
                                'id' => 'Keponakan Laki-laki',
                            ],
                        ],

                        [
                            'value' => 'niece',
                            'label' => [
                                'en' => 'Niece',
                                'id' => 'Keponakan Perempuan',
                            ],
                        ],

                        [
                            'value' => 'father_in_law',
                            'label' => [
                                'en' => 'Father-in-law',
                                'id' => 'Ayah Mertua',
                            ],
                        ],

                        [
                            'value' => 'mother_in_law',
                            'label' => [
                                'en' => 'Mother-in-law',
                                'id' => 'Ibu Mertua',
                            ],
                        ],

                        [
                            'value' => 'brother_in_law',
                            'label' => [
                                'en' => 'Brother-in-law',
                                'id' => 'Saudara Ipar Laki-laki',
                            ],
                        ],

                        [
                            'value' => 'sister_in_law',
                            'label' => [
                                'en' => 'Sister-in-law',
                                'id' => 'Saudara Ipar Perempuan',
                            ],
                        ],


                        [
                            'value' => 'others',
                            'label' => [
                                'en' => 'Others (please specify)',
                                'id' => 'Lainnya (sebutkan)',
                            ],

                            'requires' => [
                                [
                                    'key' => 'others',
                                    'type' => 'text',
                                    'label' => [
                                        'en' => 'Please specify',
                                        'id' => 'Mohon sebutkan',
                                    ],
                                ],
                            ],
                        ],

                    ],
                ],

                [
                    'key' => 'entity_name',
                    'label' => [
                        'id' => 'Bisnis Unit/PT/Divisi',
                        'en' => 'Business Unit/Company (Legal Entity)/Division',
                    ],
                ],

                [
                    'key' => 'position',
                    'label' => [
                        'id' => 'Jabatan Dalam Organisasi',
                        'en' => 'Position in the Organization',
                    ],
                ],

            ],

        ],
        [
            'key' => 'external_activities',

            'appendix' => 6,

            'title' => [
                'en' => 'I have activities outside the Company such as professional affiliations, alumni associations, religious organizations, community organizations, and/or social foundations that could create a conflict of interest or affect objectivity in carrying out my duties.',
                'id' => 'Saya memiliki kegiatan di luar Perusahaan seperti afiliasi profesi, ikatan alumni, organisasi   
                keagamaan, organisasi kemasyarakatan, dan/atau yayasan sosial yang dapat menimbulkan   
                konflik kepentingan atau mempengaruhi objektivitas dalam menjalankan tugas.',
            ],

            'appendix_title' => [
                'id' => 'LAMPIRAN 6 - DEKLARASI KONFLIK KEPENTINGAN',
                'en' => 'APPENDIX 6 - CONFLICT OF INTEREST DECLARATION',
            ],

            'appendix_subtitle' => [
                'id' => 'Tambahan Potensi Konflik Kepentingan 6',
                'en' => 'Additional Potential Conflict of Interest 6',
            ],

            'fields' => [

                [
                    'key' => 'activity_name',
                    'label' => [
                        'id' => 'Nama Kegiatan/Organisasi',
                        'en' => 'Name of Activity/Organization',
                    ],
                ],

                [
                    'key' => 'position',
                    'label' => [
                        'id' => 'Jabatan',
                        'en' => 'Position',
                    ],
                ],

                [
                    'key' => 'organization_name',
                    'label' => [
                        'id' => 'Masa Keanggotaan',
                        'en' => 'Membership Period',
                    ],
                    'type' => 'date_range',
                ],

            ],

        ],

    ],
    'pdf_footer' => [

        'paragraphs' => [

            [
                'en' => 'I declare that the information above is true and acknowledge my responsibility as an Employee to take reasonable actions to avoid conflicts of interest over such ownership. I am willing to notify the Company of any relevant situation that may create a conflict of interest in order to prevent such conflicts from occurring.',

                'id' => 'Saya menyatakan bahwa informasi di atas adalah benar dan saya memahami tanggung jawab saya sebagai Karyawan untuk mengambil langkah-langkah yang wajar guna menghindari terjadinya konflik kepentingan atas kepemilikan tersebut. Saya bersedia memberitahukan kepada Perusahaan apabila terdapat kondisi yang dapat menimbulkan konflik kepentingan agar dapat dicegah sejak dini.',
            ],

            [
                'en' => 'If, in the future, other potential conflicts of interest are discovered outside of the points mentioned above, or if any discrepancies or inaccuracies are found in the information I provided in this Conflict of Interest Declaration, I am willing to disclose additional information required and accept the consequences in accordance with the regulations, policies, and provisions applicable within the Company.',

                'id' => 'Apabila di kemudian hari ditemukan potensi konflik kepentingan lain di luar hal-hal yang telah saya nyatakan di atas, atau terdapat ketidaksesuaian maupun ketidakbenaran atas informasi yang saya sampaikan dalam Deklarasi Konflik Kepentingan ini, saya bersedia memberikan informasi tambahan yang diperlukan serta menerima konsekuensi sesuai dengan ketentuan, kebijakan, dan peraturan yang berlaku di Perusahaan.',
            ],

            [
                'en' => 'Thus, this Conflict of Interest Declaration is made and signed by me without any coercion from any party to be used and followed up as appropriate.',

                'id' => 'Demikian Deklarasi Konflik Kepentingan ini saya buat dan tandatangani dengan sebenar-benarnya tanpa adanya paksaan dari pihak mana pun untuk dipergunakan sebagaimana mestinya.',
            ],

        ],

    ],


];