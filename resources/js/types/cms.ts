export type ContentTypeInfo = {
    key: string;
    label: string;
    label_plural: string;
    icon?: string | null;
    hierarchical?: boolean;
    supports?: string[];
    url_prefix?: string | null;
};

export type ContentStatus = 'draft' | 'pending' | 'published' | 'scheduled';

export type MediaItem = {
    id: number;
    filename: string;
    mime: string;
    size: number;
    width: number | null;
    height: number | null;
    /** Wymiary oryginału (width/height dotyczą wybranego rozmiaru). */
    original_width: number | null;
    original_height: number | null;
    alt: string;
    title: string;
    caption: string | null;
    url: string;
    original_url: string;
    sizes: Record<string, { url: string; width: number; height: number }>;
    focal_x: number;
    focal_y: number;
    crops: Record<
        string,
        { x: number; y: number; width: number; height: number }
    >;
};

export type ConditionalRule = {
    field: string;
    operator: string;
    value?: string;
};

export type FieldDefinition = {
    type: string;
    name: string;
    label: string;
    instructions: string;
    required: boolean;
    conditional_logic: false | ConditionalRule[][];
    wrapper: { width: string; class: string; id: string };
    [setting: string]: unknown;
};

export type FieldGroupData = {
    id: number;
    key: string;
    title: string;
    position: 'after_title' | 'normal' | 'side';
    style: string;
    label_placement: 'top' | 'left';
    fields: FieldDefinition[];
};
