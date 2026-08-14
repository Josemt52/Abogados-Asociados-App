export const downloadBlob = (blob: Blob, filename: string): void => {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
};

export const validateFileSize = (file: File, maxSize: number): boolean => file.size <= maxSize;

export const validateFileType = (file: File, acceptedTypes: string[]): boolean => {
    const extension = `.${file.name.split('.').pop()?.toLowerCase()}`;
    const mimeType = file.type.toLowerCase();

    return acceptedTypes.some((type) => {
        const normalizedType = type.trim().toLowerCase();

        if (normalizedType.startsWith('.')) {
            return extension === normalizedType;
        }

        if (normalizedType.includes('/')) {
            return mimeType === normalizedType || mimeType.startsWith(normalizedType.replace('*', ''));
        }

        return false;
    });
};

export const formatFileSize = (bytes: number): string => {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const base = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const index = Math.floor(Math.log(bytes) / Math.log(base));
    const value = Math.round((bytes / base ** index) * 100) / 100;

    return `${value} ${sizes[index]}`;
};
