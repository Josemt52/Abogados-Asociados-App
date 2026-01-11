/**
 * Sanitiza texto para prevenir XSS
 * Convierte caracteres especiales HTML en entidades seguras
 */
export function sanitizeText(text: string): string {
  if (!text) return '';
  
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Trunca texto largo para prevención
 */
export function truncateText(text: string, maxLength: number = 1000): string {
  if (!text) return '';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
}
