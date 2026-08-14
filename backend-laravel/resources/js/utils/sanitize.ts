export const sanitizeText = (text: string): string => {
    if (!text) {
        return '';
    }

    const element = document.createElement('div');
    element.textContent = text;
    return element.innerHTML;
};

export const truncateText = (text: string, maxLength = 1000): string => {
    if (!text || text.length <= maxLength) {
        return text || '';
    }

    return `${text.substring(0, maxLength)}...`;
};
