/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./admin/**/*.php",
    "./templates/**/*.php",
    "./public/assets/js/**/*.js",
  ],
  theme: {
    extend: {},
  },
  plugins: [require("daisyui")],
  daisyui: {
    themes: ["light"],
  },
};
