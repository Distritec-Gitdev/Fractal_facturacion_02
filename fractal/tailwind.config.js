// tailwind.config.js
import preset from './tailwind.config.preset.js'; // Asegúrate de la extensión correcta

export default {
  presets: [preset],
  content: [
  './resources/views/**/*.blade.php',
  './resources/js/**/*.js',
  './app/Filament/**/*.php',
  './resources/views/vendor/filament/**/*.blade.php',
],

  // Opcional: safelista patrones comunes para garantizar que no se purguen
  safelist: [
    { pattern: /^bg-/ },
    { pattern: /^text-/ },
    { pattern: /^p-/ },
    { pattern: /^m-/ },
    { pattern: /^grid/ },
    { pattern: /^shadow/ },
    { pattern: /^rounded-/ },
  ],

   variants: {
  extend: {
    backgroundColor: ['file'],
    textColor: ['file'],
    padding: ['file'],
    borderRadius: ['file'],
  },
},

  plugins: [],

}
