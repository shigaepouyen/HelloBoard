// Feuille de style compilée (remplace le CDN cdn.tailwindcss.com, qui transmettait
// l'adresse IP des visiteurs). Après ajout de classes dans les pages :
//   npm run build:css
module.exports = {
  content: ['./public/**/*.php', './templates/**/*.php', './src/**/*.php'],
  theme: { extend: {} },
  plugins: [],
};
