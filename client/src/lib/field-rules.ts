import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";

/**
 * Canonical client-side validation rules mirroring `App\Support\ValidationRules`
 * on the backend. Both the Recruitment and CV domains compose these builders so
 * a single change (e.g. a new accepted phone format) updates every consumer.
 */

/** Canonical Iranian mobile: 09 + 9 digits. */
export const MOBILE_REGEX = /^09\d{9}$/;

/** Regex of mobile formats accepted from user input. */
export const MOBILE_ACCEPTED_REGEX = /^(09\d{9}|\+989\d{9}|00989\d{9})$/;

/** Iranian landline: 0 + 10 digits. Also matches mobiles (a subset). */
export const LANDLINE_REGEX = /^0\d{10}$/;

/** Iranian mobile or landline — the same pattern as LANDLINE (mobile is a subset). */
export const MOBILE_OR_LANDLINE_REGEX = LANDLINE_REGEX;

const MAX_PHONE_LENGTH = 15;

/** Required mobile accepting 09…, +989… or 00989… formats. */
export function mobile(
    message = "شماره موبایل الزامی است.",
    invalidMessage = "شماره موبایل نامعتبر است (مثال: 09121234567).",
) {
    return requiredText(message, MAX_PHONE_LENGTH).refine(
        (v) => MOBILE_ACCEPTED_REGEX.test(v),
        invalidMessage,
    );
}

/** Required landline. */
export function landline(message = "تلفن ثابت الزامی است.") {
    return requiredText(message, MAX_PHONE_LENGTH).refine(
        (v) => LANDLINE_REGEX.test(v),
        "فرمت تلفن ثابت صحیح نیست.",
    );
}

/** Optional landline (empty is valid). */
export function optionalLandline() {
    return text(MAX_PHONE_LENGTH, "حداکثر ۱۵ کاراکتر.").refine(
        (v) => v === "" || LANDLINE_REGEX.test(v),
        "فرمت تلفن ثابت صحیح نیست.",
    );
}

/** Required mobile or landline, e.g. an emergency contact. */
export function mobileOrLandline(message = "تلفن اضطراری الزامی است.") {
    return requiredText(message, MAX_PHONE_LENGTH).refine(
        (v) => MOBILE_OR_LANDLINE_REGEX.test(v),
        "شماره تماس اضطراری باید یک شماره موبایل یا تلفن ثابت معتبر باشد.",
    );
}

/** Optional mobile or landline (empty is valid). */
export function optionalMobileOrLandline() {
    return text(MAX_PHONE_LENGTH, "حداکثر ۱۵ کاراکتر.").refine(
        (v) => v === "" || MOBILE_OR_LANDLINE_REGEX.test(v),
        "شماره تماس اضطراری باید یک شماره موبایل یا تلفن ثابت معتبر باشد.",
    );
}

/** Required email address. */
export function email(message = "ایمیل الزامی است.") {
    return requiredText(message, 255).refine(
        (v) => z.string().email().safeParse(v).success,
        "فرمت ایمیل صحیح نیست.",
    );
}

/** Optional email (empty is valid). */
export function optionalEmail() {
    return text(255, "حداکثر ۲۵۵ کاراکتر.").refine(
        (v) => v.trim() === "" || z.string().email().safeParse(v).success,
        "فرمت ایمیل صحیح نیست.",
    );
}

/**
 * Validates the Iranian national-id checksum. Returns `true` for any non
 * 10-digit input so callers that already enforce the length can chain this
 * after a length check.
 */
export function isValidNationalId(val: string): boolean {
    if (!/^\d{10}$/.test(val)) return true;
    if (/^(\d)\1{9}$/.test(val)) return false;
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(val[i]) * (10 - i);
    }
    const remainder = sum % 11;
    const control = remainder < 2 ? remainder : 11 - remainder;
    return parseInt(val[9]) === control;
}

/** Required national id: exactly 10 digits with a valid checksum. */
export function nationalId(message = "کد ملی الزامی است.") {
    return requiredText(message, 10)
        .refine((v) => /^\d{10}$/.test(v), "کد ملی باید دقیقاً ۱۰ رقم باشد.")
        .refine(isValidNationalId, "کد ملی معتبر نیست.");
}

/** Age in whole years on a Gregorian date string (Y-m-d). */
export function getAge(birthDate: string): number {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

/** Required digits-only string, e.g. a birth certificate number. */
export function birthCertificateNumber(message = "شماره شناسنامه الزامی است.") {
    return requiredText(message, 20).refine(
        (v) => /^\d+$/.test(v),
        "شماره شناسنامه باید فقط شامل اعداد باشد.",
    );
}

/** Required postal code (10 chars). */
export function postalCode(message = "کد پستی الزامی است.") {
    return requiredText(message, 10);
}
