module.exports = {
  content: [
    "./views/indicadores/**/*.php", // ¡Añade esto para tus archivos PHP!
    "./views/templates/**/*.php", // ¡Añade esto para tus archivos PHP!
    //"./views/templates/layout/sidebar.php", // ¡Añade esto para tus archivos PHP!

    // Añade más patrones si tienes clases en otros tipos de archivos
  ],
  theme: {
    extend: {
      // Aquí puedes extender el tema de Tailwind si es necesario
    },
  },
  plugins: [
    // Aquí puedes añadir plugins de Tailwind si los usas, como @tailwindcss/forms
    require('@tailwindcss/forms'),
    require('daisyui')
  ],
  daisyui: {
    themes: ['light ', 'dark'], // puedes agregar más como 'cupcake', 'bumblebee', etc.
  },
}