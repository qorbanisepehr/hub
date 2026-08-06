import { IconCamera, IconPhoto } from "@tabler/icons-react";

import { ImageUpload } from "@/components/shared/image-upload";

type AvatarUploadProps = {
    avatarUrl: string | null;
    name: string;
    isPending?: boolean;
    onUpload: (file: File) => void;
    onDelete: () => void;
};

export function AvatarUpload({
    avatarUrl,
    name,
    isPending = false,
    onUpload,
    onDelete,
}: AvatarUploadProps) {
    return (
        <ImageUpload
            url={avatarUrl}
            alt={name}
            isPending={isPending}
            onUpload={onUpload}
            onDelete={onDelete}
            containerClassName="size-28 rounded-full"
            accept="image/jpeg,image/png,image/webp,image/gif"
            acceptedTypes={["image/jpeg", "image/png", "image/webp", "image/gif"]}
            typeErrorMessage="فقط فایل‌های تصویری (JPG, PNG, WebP, GIF) مجاز هستند."
            sizeErrorMessage="حجم فایل نباید بیشتر از ۲ مگابایت باشد."
            emptyLabel="آپلود عکس"
            deleteLabel="حذف عکس"
            deleteConfirmLabel="حذف"
            hoverOverlay={<IconCamera className="size-6 text-white" />}
            fallback={<IconPhoto className="size-7 text-muted-foreground" />}
        />
    );
}
