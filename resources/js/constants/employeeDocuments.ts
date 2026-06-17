export const EMPLOYEE_DOCUMENT_TYPES = [
    'identity',
    'national_address',
    'qualification',
    'cv',
    'iban',
] as const;

export type EmployeeDocumentType = (typeof EMPLOYEE_DOCUMENT_TYPES)[number];

export interface EmployeeDocumentItem {
    type: EmployeeDocumentType;
    url: string;
    name: string;
    mime_type: string | null;
    size: number;
    is_image: boolean;
    uploaded_at?: string | null;
}
