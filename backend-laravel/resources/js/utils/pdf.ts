const PDF_SIGNATURE = [0x25, 0x50, 0x44, 0x46, 0x2d];

export const isValidPdfBlob = async (blob: Blob): Promise<boolean> => {
    const mimeType = blob.type.toLowerCase().split(';', 1)[0].trim();

    if (mimeType !== 'application/pdf' || blob.size < PDF_SIGNATURE.length) {
        return false;
    }

    const signature = new Uint8Array(
        await blob.slice(0, PDF_SIGNATURE.length).arrayBuffer(),
    );

    return PDF_SIGNATURE.every((byte, index) => signature[index] === byte);
};

export const pdfFilename = (filename: string | null | undefined, fallback: string): string => {
    const source = filename?.trim() || fallback;
    return /\.[^.]+$/.test(source) ? source.replace(/\.[^.]+$/, '.pdf') : `${source}.pdf`;
};
