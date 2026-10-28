import { UserAvatar } from "@/components/shared/user-avatar";
import type { UnifiedInboxItemDto } from "./types";

/**
 * Sender cell per the کارتابل sample: the shared UserAvatar + name, with
 * the organizational role as the secondary line when the sender was
 * resolved to an HR Hub user; external senders show the raw name with a
 * «کاربر خارجی» hint. Shared between the cartable columns and the
 * dashboard widget.
 */
export function SenderCell({ item }: { item: UnifiedInboxItemDto }) {
    const { sender } = item;

    return (
        <div className="flex items-center gap-2 min-w-0">
            <UserAvatar
                name={sender.display_name}
                avatarUrl={sender.matched ? sender.avatar_url : null}
                size="sm"
            />
            <div className="grid min-w-0 leading-tight">
                <span className="truncate text-sm font-medium">
                    {sender.matched ? sender.display_name : sender.external_name}
                </span>
                {sender.matched && sender.org_role ? (
                    <span className="truncate text-xs text-muted-foreground">
                        {sender.org_role}
                    </span>
                ) : null}
                {!sender.matched ? (
                    <span className="text-xs text-muted-foreground">
                        کاربر خارجی
                    </span>
                ) : null}
            </div>
        </div>
    );
}
