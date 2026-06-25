export interface Declaration {
    id: number

    row_id: string

    type:
        | 'employee'
        | 'non_employee'

    name: string

    employee_id: string

    status:
        | 'pending'
        | 'submitted'

    has_conflict: boolean

    submitted_at: string | null
}