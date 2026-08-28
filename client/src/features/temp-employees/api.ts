import { api } from "@/lib/api";
import type { TempEmployee, TempFileNode } from "./types";

export type TempEmployeeListParams = {
    search?: string;
    page?: number;
    per_page?: number;
};

export type TempEmployeesMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type TempEmployeeSyncResult = {
    created: number;
    updated: number;
    skipped: string[];
};

export function fetchTempEmployees(params: TempEmployeeListParams = {}) {
    return api.get<{ data: TempEmployee[]; meta: TempEmployeesMeta }>(
        "/temp-employees",
        { params },
    );
}

export function syncTempEmployees() {
    return api.post<{ data: TempEmployeeSyncResult }>("/temp-employees/sync");
}

export function fetchTempEmployeeTree(personnelCode: string) {
    return api.get<{ data: TempFileNode[] }>(
        `/temp-employees/${personnelCode}/tree`,
    );
}

/** Inline stream URL for previews (cookie-authenticated like document serve). */
export function tempFileUrl(personnelCode: string, path: string): string {
    return `/api/temp-employees/${personnelCode}/file?path=${encodeURIComponent(path)}`;
}

/** Attachment (download) URL for a file. */
export function tempFileDownloadUrl(personnelCode: string, path: string): string {
    return `${tempFileUrl(personnelCode, path)}&download=1`;
}

/** Overwrite an existing image file on disk with an edited upload. */
export function replaceTempEmployeeFile(
    personnelCode: string,
    path: string,
    file: File,
) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("path", path);

    return api.post<{ data: TempFileNode }>(
        `/temp-employees/${personnelCode}/file`,
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
    );
}
