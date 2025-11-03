/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  transpilePackages: [],
  // Forcer l'écoute sur toutes les interfaces
  // Cela résout les problèmes de connexion localhost
};

module.exports = nextConfig;
