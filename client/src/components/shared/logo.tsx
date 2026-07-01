import { cn } from "@/lib/utils";
import type { SVGAttributes } from "react";

export const LOGO_VIEWBOX = "0 0 750 750";

export const LOGO_PATH =
    "M0 50C0 22.3858 22.3858 0 50 0H700C727.614 0 750 22.3858 750 50V700C750 727.614 727.614 750 700 750H50C22.3858 750 0 727.614 0 700V50Z";

export const LOGO_TYPE_VIEWBOX = "0 0 950 250";

export const LOGO_TYPE_PATH =
    "M0 50C0 22.3858 22.3858 0 50 0H900C927.614 0 950 22.3858 950 50V200C950 227.614 927.614 250 900 250H50C22.3857 250 0 227.614 0 200V50Z";

export function Logo({ className, ...props }: SVGAttributes<SVGSVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox={LOGO_VIEWBOX}
            {...props}
            className={cn("text-brand", className)}
        >
            <path d={LOGO_PATH} fill="currentColor" />
        </svg>
    );
}

export function LogoType({
    className,
    ...props
}: SVGAttributes<SVGSVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox={LOGO_TYPE_VIEWBOX}
            {...props}
            className={cn("text-primary", className)}
        >
            <path d={LOGO_TYPE_PATH} fill="currentColor" />
        </svg>
    );
}
