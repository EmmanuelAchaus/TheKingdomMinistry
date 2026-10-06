/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./includes/**/*.php",
    "./admin/**/*.php",
    "./assets/js/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        'sacred-navy': '#1b2b41',
        'sacred-parchment': '#fdfcf7',
        'sacred-gold': '#b89c5e',
        'gold-accent': '#c5a059',
        'parchment': '#fdfaf6',
      },
      fontFamily: {
        'serif': ['Newsreader', 'serif'],
      },
      borderRadius: {
        'custom': '4px',
        'round-four': '1rem',
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-out forwards',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(-10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        }
      }
    }
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/container-queries'),
  ],
}
