// @ts-check
import { defineConfig } from "astro/config";

import mdx from "@astrojs/mdx";

import partytown from "@astrojs/partytown";

import sitemap from "@astrojs/sitemap";

import tailwindcss from "@tailwindcss/vite";

// https://astro.build/config
export default defineConfig({
  integrations: [mdx(), partytown(), sitemap()],
  vite: {
    plugins: [tailwindcss()],
  },
});
