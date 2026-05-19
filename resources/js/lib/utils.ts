import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatEmployeeFullName(
    person?: {
        first_name?: string | null;
        father_name?: string | null;
        last_name?: string | null;
    } | null,
): string {
    if (!person) {
        return '';
    }

    return [person.first_name, person.father_name, person.last_name]
        .map((part) => (part ?? '').trim())
        .filter((part) => part.length > 0)
        .join(' ');
}

export function formatEmployeeSelectLabel(
    person: {
        first_name?: string | null;
        father_name?: string | null;
        last_name?: string | null;
        employee_id?: string | null;
    },
): string {
    const name = formatEmployeeFullName(person);

    return person.employee_id ? `${name} (${person.employee_id})` : name;
}
