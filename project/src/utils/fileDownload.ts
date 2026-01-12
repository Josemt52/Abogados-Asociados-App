export const downloadBlob = (blob: Blob, filename: string) => {
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
};

export const validateFileSize = (file: File, maxSize: number): boolean => {
  return file.size <= maxSize;
};

export const validateFileType = (file: File, acceptedTypes: string[]): boolean => {
  const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
  const fileMimeType = file.type.toLowerCase();
  
  return acceptedTypes.some(type => {
    const normalizedType = type.trim().toLowerCase();
    // Check by extension
    if (normalizedType.startsWith('.')) {
      return fileExtension === normalizedType;
    }
    // Check by MIME type
    if (normalizedType.includes('/')) {
      return fileMimeType === normalizedType || fileMimeType.startsWith(normalizedType.replace('*', ''));
    }
    return false;
  });
};

export const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes';
  
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};
