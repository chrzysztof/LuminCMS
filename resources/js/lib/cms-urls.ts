/** Adresy panelu (zgodne z routes/cms.php). */
export const cmsUrl = {
    library: '/cms/library',
    contentIndex: (type: string) => `/cms/content/${type}`,
    contentCreate: (type: string) => `/cms/content/${type}/create`,
    contentEdit: (type: string, id: number) =>
        `/cms/content/${type}/${id}/edit`,
    contentBuilder: (type: string, id: number) =>
        `/cms/content/${type}/${id}/builder`,
    contentUpdate: (type: string, id: number) => `/cms/content/${type}/${id}`,
};
