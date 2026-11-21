import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/features/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          blue: "#1E3A8A",
          dark: "#172554",
          teal: "#0D9488",
          amber: "#F59E0B",
        },
        surface: {
          light: "#FFFFFF",
          subtle: "#F8FAFC",
        },
        status: {
          success: "#16A34A",
          warning: "#D97706",
          danger: "#DC2626",
          info: "#2563EB",
        },
        pub: {
          navy: "#0A3D62",
          "navy-deep": "#062940",
          "navy-light": "#155B8A",
          "navy-subtle": "#EBF3F8",
          gold: "#C98A1F",
          "gold-hover": "#B27615",
          "gold-soft": "#F4E2BD",
          "gold-subtle": "#FEF9F0",
          green: "#1E7A46",
          "green-soft": "#C3E6D0",
          "bg-app": "#F6F7F9",
          "text-main": "#1B2430",
          "text-muted": "#4A5563",
          border: "#DFE3E8",
        },
      },
      fontFamily: {
        "pub-heading": ["var(--font-fraunces)", "Georgia", "serif"],
        "pub-sans": ["var(--font-inter)", "system-ui", "sans-serif"],
      },
      borderRadius: {
        sm: "4px",
        md: "8px",
        lg: "12px",
        xl: "16px",
      },
      boxShadow: {
        card: "0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03)",
        elevated: "0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04)",
      },
    },
  },
  plugins: [],
};

export default config;
