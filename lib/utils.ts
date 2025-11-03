import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

// Utiliser 127.0.0.1 au lieu de localhost pour éviter les problèmes DNS
export const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:3001';

