export interface ToolSection {
    key: string;
    label: string;
    badge?: number | null;
}

// Unknown or unavailable sections fall back to the first permitted destination.
export function resolveSection(
    requested: unknown,
    sections: ToolSection[]
): string {
    return sections.some((section) => section.key === requested)
        ? (requested as string)
        : (sections[0]?.key ?? '');
}
