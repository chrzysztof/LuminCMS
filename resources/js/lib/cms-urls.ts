/** Adresy panelu (zgodne z routes/cms.php). */
export const cmsUrl = {
    library: '/cms/library',
    seoSettings: '/cms/settings/seo',
    siteSettings: '/cms/settings/site',
    redirectIndex: '/cms/redirects',
    redirectLogs: '/cms/redirects/logs',
    redirectPages: '/cms/redirects/pages',
    redirectUpdate: (id: number) => `/cms/redirects/${id}`,
    redirectToggle: (id: number) => `/cms/redirects/${id}/toggle`,
    redirectLog: (id: number) => `/cms/redirects/logs/${id}`,
    fieldIndex: '/cms/fields',
    fieldCreate: '/cms/fields/create',
    fieldImport: '/cms/fields/import',
    fieldEdit: (id: number) => `/cms/fields/${id}/edit`,
    fieldUpdate: (id: number) => `/cms/fields/${id}`,
    fieldToggle: (id: number) => `/cms/fields/${id}/toggle`,
    fieldDuplicate: (id: number) => `/cms/fields/${id}/duplicate`,
    fieldExport: (id: number) => `/cms/fields/${id}/export`,
    contentIndex: (type: string) => `/cms/content/${type}`,
    contentCreate: (type: string) => `/cms/content/${type}/create`,
    contentEdit: (type: string, id: number) =>
        `/cms/content/${type}/${id}/edit`,
    contentBuilder: (type: string, id: number) =>
        `/cms/content/${type}/${id}/builder`,
    contentUpdate: (type: string, id: number) => `/cms/content/${type}/${id}`,
};
