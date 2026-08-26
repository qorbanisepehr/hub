import { api } from "@/lib/api";
import type { TempEmployee, TempFileNode } from "./types";

export function fetchTempEmployees() {
    return api.get<{ data: TempEmployee[] }>("/temp-employees");
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
