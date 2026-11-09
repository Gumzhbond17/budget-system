import axios from 'axios';
import type { AxiosInstance } from 'axios';

/**
 * Axios instance ດຽວທີ່ທຸກບ່ອນຕ້ອງໃຊ້.
 *
 * Sanctum SPA mode:
 *   withCredentials  → ສົ່ງ session cookie ໄປນຳທຸກ request
 *   XSRF-TOKEN       → axios ອ່ານ cookie ນີ້ແລ້ວໃສ່ header X-XSRF-TOKEN ໃຫ້ເອງ
 *
 * SPA ກັບ API ຢູ່ໂດເມນດຽວກັນຈຶ່ງບໍ່ຕ້ອງຕັ້ງ CORS ເລີຍ.
 */
export const api: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/** ໃຊ້ກັບ route ນອກ /api/v1 (login, logout, csrf-cookie) */
export const web: AxiosInstance = axios.create({
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/**
 * ດຶງ CSRF cookie ກ່ອນ request ທຳອິດທີ່ຂຽນຂໍ້ມູນ.
 * ຮ້ອງຊ້ຳໄດ້ບໍ່ເປັນຫຍັງ ແຕ່ຮ້ອງເທື່ອດຽວກໍພຽງພໍ.
 */
let csrfPromise: Promise<unknown> | null = null;

export function ensureCsrfCookie(): Promise<unknown> {
    csrfPromise ??= web.get('/sanctum/csrf-cookie');

    return csrfPromise;
}

/** ລ້າງ cache ຂອງ CSRF ຫຼັງ logout — token ເກົ່າໃຊ້ບໍ່ໄດ້ອີກ */
export function resetCsrfCookie(): void {
    csrfPromise = null;
}

/**
 * ຮູບແບບ error ຂອງ Laravel ທີ່ SPA ຕ້ອງຮັບມື.
 */
export interface ApiError {
    status: number;
    message: string;
    /** ຂໍ້ຜິດພາດຕໍ່ field ຈາກ 422 */
    errors: Record<string, string[]>;
}

export function toApiError(error: unknown): ApiError {
    if (axios.isAxiosError(error) && error.response) {
        const data = error.response.data as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        return {
            status: error.response.status,
            message: data?.message ?? 'ເກີດຂໍ້ຜິດພາດ',
            errors: data?.errors ?? {},
        };
    }

    return { status: 0, message: 'ຕິດຕໍ່ server ບໍ່ໄດ້', errors: {} };
}

/** ຂໍ້ຄວາມທຳອິດຂອງ field — ໃຊ້ໃຕ້ຊ່ອງ input */
export function firstError(
    errors: Record<string, string[]>,
    field: string,
): string | null {
    return errors[field]?.[0] ?? null;
}
