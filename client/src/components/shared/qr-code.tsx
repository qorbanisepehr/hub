import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from "react";
import QRCodeStyling from "qr-code-styling";

import { LOGO_PATH, LOGO_VIEWBOX } from "./logo";

const generateLogoSVG = (color: string): string => {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${LOGO_VIEWBOX}" fill="none"><path d="${LOGO_PATH}" fill="${color}"/></svg>`;
    const encoded = encodeURIComponent(svg).replace(
        /%([0-9A-F]{2})/g,
        (_, p1) => String.fromCharCode(parseInt(p1, 16)),
    );
    return `data:image/svg+xml;base64,${btoa(encoded)}`;
};

type QrCodeProps = {
    value: string;
    size?: number;
    color?: string;
};

export type QrCodeRef = {
    download: (name?: string) => void;
};

export const QrCode = forwardRef<QrCodeRef, QrCodeProps>(function QrCode(
    { value, size = 256, color = "hsl(222.2 84% 4.9%)" },
    ref,
) {
    const containerRef = useRef<HTMLDivElement>(null);
    const logo = generateLogoSVG("#db7868");

    const [qrCode] = useState(
        () =>
            new QRCodeStyling({
                type: "svg",
                width: size,
                height: size,
                data: value,
                image: logo,
                margin: 2,
                qrOptions: {
                    typeNumber: 0,
                    mode: "Byte",
                    errorCorrectionLevel: "Q",
                },
                imageOptions: {
                    hideBackgroundDots: true,
                    imageSize: 0.4,
                    margin: 1.5,
                    crossOrigin: "anonymous",
                },
                dotsOptions: {
                    type: "rounded",
                    color,
                    roundSize: true,
                },
                cornersSquareOptions: {
                    type: "rounded",
                    color,
                },
                cornersDotOptions: {
                    type: "rounded",
                    color,
                },
                backgroundOptions: {
                    round: 0,
                    color: "transparent",
                },
            }),
    );

    useEffect(() => {
        if (value) {
            qrCode.update({ data: value, image: logo });
        }
    }, [qrCode, value, logo]);

    useEffect(() => {
        if (containerRef.current) {
            containerRef.current.innerHTML = "";
            qrCode.append(containerRef.current);
        }
    }, [qrCode]);

    useImperativeHandle(ref, () => ({
        download: (name = "qr-code") => {
            qrCode.download({ name, extension: "png" });
        },
    }));

    return <div ref={containerRef} style={{ width: size, height: size }} />;
});
