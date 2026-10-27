export type Localized = { en: string, id: string }

/** A form type as offered in filters (FormType::options). */
export interface FormTypeOption {
    id: number
    code: string
    name: Localized
    is_active: boolean
}

export interface FormVariable {
    key: string
    label: Localized
}

/** Paper, margins (mm) and default font (FormTemplate::pageLayout). */
export interface PageLayout {
    paper_size: string
    orientation: 'portrait' | 'landscape'
    width: number
    height: number
    margin_top: number
    margin_right: number
    margin_bottom: number
    margin_left: number
    font_family: string
    // CSS font-family value for font_family (config/forms.php).
    font_css: string
    font_size: number
    line_height: number
}

/** Font choices offered by the editor (config/forms.php). */
export interface FontOptions {
    fonts: { key: string, label: string, css: string }[]
    sizes: number[]
    lineHeights: number[]
    textColors: string[]
    highlightColors: string[]
}

/** A starting point from config/form_template_samples.php. */
export interface FormSample {
    key: string
    // Code of the form type this sample belongs to; none = any type.
    form_type?: string
    name: Localized
    title_en: string
    title_id: string
    content_en: string
    content_id: string
    // Optional: a sample may also bring its own page setup.
    header_en?: string
    header_id?: string
    footer_en?: string
    footer_id?: string
    paper_size?: string
    orientation?: 'portrait' | 'landscape'
    margin_top?: number
    margin_right?: number
    margin_bottom?: number
    margin_left?: number
    font_family?: string
    font_size?: number
    line_height?: number
}

/** A template rendered in both languages (FormDeclarationService::renderBilingual). */
export interface RenderedForm {
    title: Localized
    content: Localized
    header: Localized
    footer: Localized
    page: PageLayout
}

/** A row's status. 'pending' = not started. */
export type FormStatus = 'signed' | 'draft' | 'pending' | 'no_template'

/** Status filter values: a row status, or 'not_signed' (pending + draft). */
export type FormStatusFilter = FormStatus | 'not_signed'

/** One person in the report / dashboard / team view (FormReportService). */
export interface FormRow {
    key: string
    period: number
    type: 'employee' | 'non_employee'
    user_id: number
    name: string
    employee_id: string
    business_unit: string | null
    contribution_level: string | null
    designation: string | null
    level: string | null
    work_location: string | null
    status: FormStatus
    template_id: number | null
    template_title: Localized | null
    declaration_id: number | null
    signed_name: string | null
    signed_at: string | null
    signed_locale: 'en' | 'id' | null
}

export interface FormStats {
    total: number
    signed: number
    draft: number
    // Owes a signature, but has not opened/saved anything yet.
    not_started: number
    // not_started + draft: everyone who still has to sign.
    not_signed: number
    no_template: number
    rate: number
}

export interface Paginated<T> {
    data: T[]
    links: { url: string | null, label: string, active: boolean }[]
    per_page: number
    total: number
    from: number | null
    to: number | null
    current_page: number
}
