export type BrandingSettings = {
    name: string;
    sub_name: string;
    logo_url: string | null;
    logotype_url: string | null;
    favicon_url: string | null;
    og_image_url: string | null;
    logo_svg: string | null;
    logotype_svg: string | null;
    primary_color: string;
    secondary_color: string;
    version: number;
};

export type UpdateBrandingData = {
    name: string;
    sub_name: string;
    primary_color: string;
    secondary_color: string;
};
