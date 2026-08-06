/// <reference types="vite/client" />
interface ImportMetaEnv {
    readonly VITE_API_BASE_URL: string;
    readonly VITE_CO_NAME: string;
    readonly VITE_CO_SUB_NAME: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}

interface Window {
    __BRANDING__?: {
        name: string;
        sub_name: string;
        logo_url: string | null;
        logotype_url: string | null;
        favicon_url: string | null;
        og_image_url: string | null;
        logo_svg?: string | null;
        logotype_svg?: string | null;
        primary_color: string;
        secondary_color: string;
        version: number;
    };
}
