/**
 * Vérifie si un port est disponible (version frontend simplifiée)
 * Note: Cette vérification se fait côté serveur Next.js
 */

export function getFrontendPort(): number {
  const envPort = process.env.PORT || process.env.NEXT_PUBLIC_PORT;
  if (envPort) {
    return parseInt(envPort);
  }
  
  // Ports par défaut avec fallback
  const defaultPort = 3000;
  const fallbackPorts = [3001, 3002, 3003, 4000, 4001, 5000];
  
  // En Next.js, on ne peut pas vérifier avant, mais on peut essayer
  // et laisser Next.js gérer l'erreur
  return defaultPort;
}

