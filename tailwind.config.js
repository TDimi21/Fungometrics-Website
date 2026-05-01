/** @type {import('tailwindcss').Config} */

module.exports = {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./resources/**/*.{html,js}",

],
  theme: {
    extend: {
      colors: {
        "fungo-darkblue-player": "#26364D",
        "fungo-darkblue": "#082247",
        "fungo-darkblue-hover" : "#194585",
        "fungo-darkblug-disabled": "#88A2C9",
        "fungo-dark-gray": "#A6A6A6",
        "fungo-light-gray": "#DADADA",

        "fungo-blue": "#0096C7",
        "fungo-blue2": "#0077B6",
        "fungo-blue3": "#ADE8F4",
        "fungo-blue4": "#DBDFF1",
        "fungo-blue-hover" : "#006CA6",
        "fungo-blug-disabled": "#98C9E3",


        "fungo-red": "#E10600",
        "fungo-red-hover": "#BA0500",
        "fungo-red-disabled": "#F6837F",


        "fungo-gray": "#D9D9D9",
        "fungo-gray2": "#E7EAEE",
        "fungo-gray3": "#E1E3E7",
        "fungo-gray4": "#F7F8F9",
        "fungo-gray5": "#D3D3D3",
        "fungo-gray6": "#DBDFF1",
        "fungo-gray7": "#F6F6F6",
        "fungo-gray8": "#DFDFDF;",
        "fungo-gray9": "#D8DEE7;",


        "fungo-lightblue": "#ADE8F4",
        "fungo-lightblue-hover": "#ADE8F4",
        "fungo-lightblue-disabled": "#D7F3F9"
      },
      boxShadow: {
        'fungo-but-shadow': '0 10px 10px 5px rgba(0, 0, 0, 0.3),0 -5px 10px 0px' +
          ' rgba(0, 0, 0, 0.3)',

      }
    },

    fontFamily: {
      'fungo-poppins': ['Poppins', 'sans-serif']
    },

    fontWeight: {
      'fungo-300': 300,
      'fungo-500': 500,
      'fungo-700': 700,
      'fungo-800': 800
    }
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
