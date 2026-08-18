import {
    IconClipboard,
    IconFileDescription,
    IconFiles,
    IconFolder,
    IconHeadset,
    IconLayoutDashboard,
    IconLogin,
    IconUserScan,
    IconUsersGroup,
} from "@tabler/icons-react";

export const SERVICES = [
    {
        icon: IconFolder,
        title: "مدیریت اسناد",
        desc: "ذخیره، دسته‌بندی و جستجوی پیشرفته انواع اسناد سازمانی",
    },
    {
        icon: IconUsersGroup,
        title: "مدیریت پرسنل",
        desc: "ثبت و نگهداری اطلاعات جامع پرسنل و سوابق کاری",
    },
    {
        icon: IconFileDescription,
        title: "بایگانی دیجیتال",
        desc: "دیجیتال‌سازی و بایگانی هوشمند پرونده‌های فیزیکی",
    },
    {
        icon: IconUserScan,
        title: "احراز هویت امن",
        desc: "ورود امن با رمز یکبار مصرف یا رمز عبور برای کاربران مجاز",
    },
] as const;

export const REASONS = [
    {
        icon: IconFiles,
        title: "دسترسی سریع",
        desc: "جستجوی پیشرفته و دسترسی آنی به اسناد و پرونده‌ها",
    },
    {
        icon: IconUsersGroup,
        title: "دسترسی سطح‌بندی شده",
        desc: "تعریف نقش‌های دسترسی متفاوت برای کاربران سازمان",
    },
    {
        icon: IconFileDescription,
        title: "بایگانی هوشمند",
        desc: "دسته‌بندی خودکار و بازیابی سریع اطلاعات بایگانی شده",
    },
] as const;

export const AUTH_BUTTON_ICONS = {
    authenticated: IconLayoutDashboard,
    unauthenticated: IconLogin,
    contact: IconHeadset,
    questionnaire: IconClipboard,
} as const;
