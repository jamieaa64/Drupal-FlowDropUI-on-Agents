/**
 * Vite Configuration for Production IIFE Build
 * 
 * @description
 * This configuration builds the FlowDrop library as an IIFE for production use.
 * It re-exports all functionality from @d34dman/flowdrop and bundles it into a single
 * self-contained JavaScript file that can be loaded via <script> tags.
 * 
 * Output: build/flowdrop/flowdrop.iife.js (and flowdrop.es.js)
 * Global: window.FlowDrop
 */

import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import path from "path";

export default defineConfig({
	plugins: [
		svelte({
			// Compile Svelte components from node_modules
			compilerOptions: {
				dev: false
			},
			// Enable processing of Svelte files from node_modules/@d34dman/flowdrop
			onwarn: (warning, handler) => {
				// Suppress warnings from dependencies
				if (warning.filename?.includes("node_modules")) {
					return;
				}
				handler(warning);
			}
		})
	],
	resolve: {
		alias: {
			$lib: path.resolve("./src/lib"),
			// Mock SvelteKit-specific imports for library build
			"$app/stores": path.resolve("./src/lib/mocks/app-stores.js"),
			"$app/forms": path.resolve("./src/lib/mocks/app-forms.js"),
			"$app/navigation": path.resolve("./src/lib/mocks/app-navigation.js"),
			"$app/environment": path.resolve("./src/lib/mocks/app-environment.js")
		},
		// Ensure we can resolve Svelte components from node_modules
		conditions: ["svelte", "browser", "import"],
		dedupe: ["svelte"]
	},
	build: {
		outDir: "build/flowdrop",
		lib: {
			entry: "src/lib/index.ts",
			name: "FlowDrop",
			fileName: (format) => `flowdrop.${format}.js`,
			formats: ["iife", "es"]
		},
		rollupOptions: {
			// Bundle all dependencies including @d34dman/flowdrop to create a self-contained IIFE
			// No external dependencies - everything is bundled
			external: [],
			output: {
				// Global variable name for IIFE (window.FlowDrop)
				globals: {},
				// Ensure CSS is extracted to a separate file
				assetFileNames: (assetInfo) => {
					if (assetInfo.name?.endsWith(".css")) {
						return "flowdrop.css";
					}
					return assetInfo.name || "assets/[name]-[hash][extname]";
				},
				// Inline dynamic imports to avoid chunk splitting
				inlineDynamicImports: true
			}
		},
		// Generate source maps for debugging
		sourcemap: true,
		// Minify for production using terser
		minify: "terser",
		terserOptions: {
			compress: {
				drop_console: false,
				drop_debugger: true
			}
		},
		// Target modern browsers
		target: "es2015",
		// Increase chunk size warning limit for bundled IIFE
		chunkSizeWarningLimit: 2000,
		// Ensure CommonJS is handled properly
		commonjsOptions: {
			include: [/node_modules/],
			transformMixedEsModules: true
		}
	},
	define: {
		"process.env.NODE_ENV": JSON.stringify("production"),
		"process.env": "{}",
		// Set environment variables for production build
		"import.meta.env.MODE": JSON.stringify("production"),
		"import.meta.env.DEV": "false",
		"import.meta.env.PROD": "true",
		"import.meta.env.SSR": "false"
	},
	// Disable environment variable exposure
	envPrefix: "__FLOWDROP_DISABLED__",
	// Optimize dependencies - bundle everything including @d34dman/flowdrop
	optimizeDeps: {
		include: ["@d34dman/flowdrop", "svelte", "@xyflow/svelte", "@iconify/svelte", "uuid"],
		exclude: []
	}
});
