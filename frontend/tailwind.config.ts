import type { Config } from 'tailwindcss';

export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      /* Ориентир: trendagent.ru — #191919 текст, 22px/27px заголовки, 1180px контейнер */
      colors: {
        ta: {
          text: '#191919',
          'text-muted': '#333333',
          border: '#e5e5e5',
          surface: '#fcfcfc',
        },
      },
      fontFamily: {
        sans: [
          'BlinkMacSystemFont',
          '-apple-system',
          '"Segoe UI"',
          'Roboto',
          'Oxygen',
          'Ubuntu',
          'Cantarell',
          '"Open Sans"',
          '"Helvetica Neue"',
          'sans-serif',
        ],
      },
      maxWidth: {
        'ta-container': '1180px',
      },
      fontSize: {
        'ta-h1': ['22px', { lineHeight: '27px' }],
        'ta-h2': ['18px', { lineHeight: '24px' }],
        'ta-h3': ['16px', { lineHeight: '22px' }],
      },
    },
  },
  plugins: [],
} satisfies Config;

