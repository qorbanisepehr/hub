export const FILE_UPLOAD = {
    MAX_SIZE: 50 * 1024 * 1024, // 50 MB
    MAX_SIZE_AVATAR: 2 * 1024 * 1024, // 2 MB
    MAX_FILES_BULK: 20,
} as const;

export const PAGINATION = {
    DEFAULT_PAGE_SIZE: 10,
    PAGE_SIZE_OPTIONS: [10, 15, 30, 50],
    SEARCH_PAGE_SIZE: 20,
    FETCH_ALL_SIZE: 100,
} as const;

export const DEBOUNCE = {
    SEARCH: 300,
} as const;
