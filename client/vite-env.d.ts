/// <reference types="vite/client" />
interface ImportMetaEnv {
    readonly VITE_API_BASE_URL: string;
    readonly VITE_CO_NAME: string;
    readonly VITE_CO_SUB_NAME: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
