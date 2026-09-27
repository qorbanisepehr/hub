import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";

/**
 * Canonical client-side validation rules mirroring `App\Support\ValidationRules`
 * on the backend. Both the Questionnaire and CV domains compose these builders so
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
export function isValidIdNumber(val: string): boolean {
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
export function idNumber(message = "کد ملی الزامی است.") {
    return requiredText(message, 10)
        .refine((v) => /^\d{10}$/.test(v), "کد ملی باید دقیقاً ۱۰ رقم باشد.")
        .refine(isValidIdNumber, "کد ملی معتبر نیست.");
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

/** Required postal code: exactly 10 digits. */
export function postalCode(message = "کد پستی الزامی است.") {
    return requiredText(message, 10).refine(
        (v) => /^\d{10}$/.test(v),
        "کد پستی باید دقیقاً ۱۰ رقم باشد.",
    );
}

/** Optional postal code (empty is valid, non-empty must be 10 digits). */
export function optionalPostalCode() {
    return text(10, "حداکثر ۱۰ کاراکتر.").refine(
        (v) => v === "" || /^\d{10}$/.test(v),
        "کد پستی باید دقیقاً ۱۰ رقم باشد.",
    );
}

/**
 * Luhn checksum over a digit string.
 */
export function isValidLuhn(digits: string): boolean {
    let sum = 0;
    let double = false;
    for (let i = digits.length - 1; i >= 0; i--) {
        let d = Number(digits[i]);
        if (double) {
            d *= 2;
            if (d > 9) d -= 9;
        }
        sum += d;
        double = !double;
    }
    return sum % 10 === 0;
}

/**
 * Validates an Iranian bank card number: 16 digits starting with 6 and a
 * valid Luhn checksum. Returns `true` for any other-shaped input so callers
 * can chain it after a format check.
 */
export function isValidCardNumber(val: string): boolean {
    if (!/^6\d{15}$/.test(val)) return false;
    return isValidLuhn(val);
}

/** Required bank card number (16 digits + Luhn). */
export function cardNumber(message = "شماره کارت الزامی است.") {
    return requiredText(message, 30)
        .refine(
            (v) => /^6\d{15}$/.test(v),
            "شماره کارت باید ۱۶ رقم و با عدد ۶ شروع شود.",
        )
        .refine(isValidLuhn, "شماره کارت معتبر نیست.");
}

/** Optional bank card number (empty is valid). */
export function optionalCardNumber() {
    return text(30).refine(
        (v) => v === "" || isValidCardNumber(v),
        "شماره کارت معتبر نیست (۱۶ رقم با پیشوند ۶).",
    );
}

/**
 * Validates an Iranian IBAN: "IR" + 24 digits passing ISO 7064 MOD-97-10.
 */
export function isValidIban(val: string): boolean {
    const normalized = val.toUpperCase().replace(/[\s-]/g, "");
    if (!/^IR\d{24}$/.test(normalized)) return false;
    // Move IR + check digits to the end (I=18, R=27) and run rolling mod-97.
    const rearranged = normalized.slice(4) + "1827" + normalized.slice(2, 4);
    let rem = 0;
    for (const ch of rearranged) {
        rem = (rem * 10 + Number(ch)) % 97;
    }
    return rem === 1;
}

/** Required IBAN/شبا (IR + 24 digits + mod-97 checksum). */
export function iban(message = "شماره شبا الزامی است.") {
    return requiredText(message, 30)
        .refine(
            (v) => /^IR\d{24}$/.test(v.toUpperCase().replace(/[\s-]/g, "")),
            "شماره شبا باید با IR شروع شده و ۲۴ رقم بعد از آن بیاید.",
        )
        .refine(isValidIban, "شماره شبا معتبر نیست.");
}

/** Optional IBAN/شبا (empty is valid). */
export function optionalIban() {
    return text(30).refine(
        (v) => v === "" || isValidIban(v),
        "شماره شبا معتبر نیست (IR و ۲۴ رقم).",
    );
}
