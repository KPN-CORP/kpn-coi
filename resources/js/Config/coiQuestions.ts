export interface QuestionField {
    key: string
    label: {
        en: string
        id: string
    }
    type: 'text'
}

export interface CoiQuestion {
    key: string
    title: {
        en: string
        id: string
    }
    fields: QuestionField[]
}

export const coiQuestions: CoiQuestion[] = [
    {
        key: 'business_affiliation',
        title: {
            en: '1. I have business ownership/affiliation with KPN entity.',
            id: '1. Saya memiliki kepemilikan bisnis/afiliasi dengan entitas grup KPN.',
        },
        fields: [
            {
                key: 'entity_name',
                label: {
                    en: 'Entity Name',
                    id: 'Nama Entitas',
                },
                type: 'text',
            },
            {
                key: 'ownership_year',
                label: {
                    en: 'Ownership Year',
                    id: 'Tahun Kepemilikan',
                },
                type: 'text',
            },
            {
                key: 'entity_owner',
                label: {
                    en: 'Entity Owner',
                    id: 'Pemilik Entitas',
                },
                type: 'text',
            },
            {
                key: 'relationship',
                label: {
                    en: 'Relationship',
                    id: 'Hubungan',
                },
                type: 'text',
            },
        ],
    },

    {
        key: 'professional_relationship',
        title: {
            en: '2. I have a professional relationship with an entity connected to KPN.',
            id: '2. Saya memiliki hubungan profesional dengan entitas yang memiliki hubungan bisnis dengan KPN.',
        },
        fields: [
            {
                key: 'entity_name',
                label: {
                    en: 'Entity Name',
                    id: 'Nama Entitas',
                },
                type: 'text',
            },
            {
                key: 'relationship_type',
                label: {
                    en: 'Relationship Type',
                    id: 'Jenis Hubungan',
                },
                type: 'text',
            },
            {
                key: 'work_period',
                label: {
                    en: 'Work Period',
                    id: 'Masa Kerja',
                },
                type: 'text',
            },
            {
                key: 'related_bu',
                label: {
                    en: 'Related KPN BU',
                    id: 'BU KPN Terkait',
                },
                type: 'text',
            },
        ],
    },

    {
        key: 'equity_ownership',

        title: {
            en: '3. I have share/equity ownership causing potential conflict.',
            id: '3. Saya memiliki kepemilikan saham/ekuitas yang berpotensi konflik.',
        },

        fields: [
            {
                key: 'ownership_type',
                label: {
                    en: 'Ownership Type',
                    id: 'Jenis Kepemilikan',
                },
                type: 'text',
            },

            {
                key: 'equity_name',
                label: {
                    en: 'Equity Name',
                    id: 'Nama Ekuitas',
                },
                type: 'text',
            },

            {
                key: 'amount_percentage',
                label: {
                    en: 'Amount (%)',
                    id: 'Jumlah (%)',
                },
                type: 'text',
            },
        ],
    },

    {
        key: 'gifts_benefits',

        title: {
            en: '4. I receive gifts from KPN business parties.',
            id: '4. Saya menerima hadiah/manfaat dari pihak bisnis KPN.',
        },

        fields: [
            {
                key: 'giver_name',
                label: {
                    en: 'Giver Name',
                    id: 'Nama Pihak Pemberi',
                },
                type: 'text',
            },

            {
                key: 'goods_services',
                label: {
                    en: 'Goods / Services',
                    id: 'Jenis Barang / Jasa',
                },
                type: 'text',
            },

            {
                key: 'amount_value',
                label: {
                    en: 'Amount / Value',
                    id: 'Jumlah / Nominal',
                },
                type: 'text',
            },
        ],
    },

    {
        key: 'family_relationship',

        title: {
            en: '5. I have family relationships with other KPN employees.',
            id: '5. Saya memiliki hubungan keluarga dengan karyawan lain di KPN.',
        },

        fields: [
            {
                key: 'family_name',
                label: {
                    en: 'Family Name',
                    id: 'Nama Keluarga',
                },
                type: 'text',
            },

            {
                key: 'relationship',
                label: {
                    en: 'Relationship',
                    id: 'Hubungan Keluarga',
                },
                type: 'text',
            },

            {
                key: 'business_unit',
                label: {
                    en: 'Business Unit',
                    id: 'Bisnis Unit / PT',
                },
                type: 'text',
            },

            {
                key: 'position',
                label: {
                    en: 'Position',
                    id: 'Jabatan',
                },
                type: 'text',
            },
        ],
    },

    {
        key: 'external_activities',

        title: {
            en: '6. I have outside activities causing potential conflict.',
            id: '6. Saya memiliki kegiatan di luar perusahaan yang berpotensi konflik.',
        },

        fields: [
            {
                key: 'organization_name',
                label: {
                    en: 'Organization',
                    id: 'Nama Organisasi',
                },
                type: 'text',
            },

            {
                key: 'position',
                label: {
                    en: 'Position',
                    id: 'Jabatan',
                },
                type: 'text',
            },

            {
                key: 'membership_period',
                label: {
                    en: 'Membership Period',
                    id: 'Masa Keanggotaan',
                },
                type: 'text',
            },
        ],
    },
]