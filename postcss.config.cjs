// postcss.config.cjs
module.exports = {
  plugins: {
    // Ahora usamos el plugin dedicado:
    '@tailwindcss/postcss': {},
    // Y autoprefixer sigue igual
    autoprefixer: {},
  },
}
