import { useRef } from "react";
import { toast } from "sonner";
import {
    IconShare,
    IconLink,
    IconDownload,
    IconBrandWhatsapp,
    IconBrandTelegram,
    IconMail,
    IconDeviceMobile,
} from "@tabler/icons-react";

import { Button, buttonVariants } from "@/components/ui/button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { QrCode, type QrCodeRef } from "@/components/shared/qr-code";

type ShareDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    title?: string;
};

export function ShareDialog({
    open,
    onOpenChange,
    url,
    title = "اشتراک‌گذاری لینک",
}: ShareDialogProps) {
    const qrRef = useRef<QrCodeRef>(null);

    const handleCopyLink = async () => {
        await navigator.clipboard.writeText(url);
        toast.success("لینک کپی شد.");
    };

    const handleSystemShare = async () => {
        try {
            await navigator.share({
                title: "پرسشنامه استخدامی",
                text: "لطفاً فرم پرسشنامه را تکمیل کنید.",
                url,
            });
        } catch {
            await navigator.clipboard.writeText(url);
            toast.success("لینک کپی شد.");
        }
    };

    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent("پرسشنامه استخدامی\n" + url)}`;
    const telegramUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent("پرسشنامه استخدامی")}`;
    const smsUrl = `sms:?body=${encodeURIComponent("پرسشنامه استخدامی " + url)}`;
    const emailUrl = `mailto:?subject=${encodeURIComponent("پرسشنامه استخدامی")}&body=${encodeURIComponent(url)}`;

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description="لینک پرسشنامه را با نامزد به اشتراک بگذارید"
            footer={
                <>
                    <Button
                        variant="outline"
                        size="sm"
                        className="flex-1"
                        onClick={() => qrRef.current?.download("qr-code")}
                    >
                        <IconDownload className="size-4" />
                        دانلود QR
                    </Button>
                    <Button
                        size="sm"
                        className="flex-1"
                        onClick={handleCopyLink}
                    >
                        <IconLink className="size-4" />
                        کپی لینک
                    </Button>
                </>
            }
        >
            <div className="flex flex-col items-center gap-4">
                {/* QR Code */}
                <div className="rounded-xl border bg-background p-0.5">
                    <QrCode ref={qrRef} value={url} size={192} />
                </div>

                {/* URL */}
                <p
                    className="w-full truncate rounded-lg bg-muted px-3 py-2 text-center text-xs text-muted-foreground select-all"
                    dir="ltr"
                >
                    {url}
                </p>

                {/* Share Actions */}
                <div className="flex w-full flex-col gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-full justify-start"
                        onClick={handleSystemShare}
                    >
                        <IconShare className="size-4" />
                        اشتراک‌گذاری
                    </Button>
                    <div className="grid grid-cols-2 gap-2">
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                            })}
                        >
                            <IconBrandWhatsapp className="size-4" />
                            WhatsApp
                        </a>
                        <a
                            href={telegramUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                            })}
                        >
                            <IconBrandTelegram className="size-4" />
                            Telegram
                        </a>
                        <a
                            href={emailUrl}
                            className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                            })}
                        >
                            <IconMail className="size-4" />
                            ایمیل
                        </a>
                        <a
                            href={smsUrl}
                            className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                            })}
                        >
                            <IconDeviceMobile className="size-4" />
                            پیامک
                        </a>
                    </div>
                </div>
            </div>
        </ResponsiveDialog>
    );
}
