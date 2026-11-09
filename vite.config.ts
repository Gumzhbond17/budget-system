import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';
import Icons from 'unplugin-icons/vite';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        /*
         * Iconify ແບບ compile-time — ໄອຄອນຖືກຝັງເປັນ SVG ໃນ bundle ຕອນ build.
         *
         * ບໍ່ໃຊ້ @iconify/vue ແບບຕັ້ງຕົ້ນທີ່ດຶງຈາກ api.iconify.design ຕອນ runtime:
         * ຜູ້ໃຊ້ຢູ່ລາວຈະລໍຖ້າ request ຂ້າມປະເທດທຸກຄັ້ງທີ່ໂຫຼດໜ້າ ແລະ ໃຊ້ offline ບໍ່ໄດ້.
         * ແບບນີ້ tree-shake ໃຫ້ນຳ — ໄອຄອນທີ່ບໍ່ໄດ້ import ບໍ່ຖືກລວມເຂົ້າ bundle.
         *
         * ໃຊ້: import IconSun from '~icons/tabler/sun'
         */
        Icons({
            compiler: 'vue3',
            scale: 1,
        }),
    ],
    resolve: {
        // '@' ຊີ້ໄປ resources/js ຄືກັນກັບ template ເດີມ — import ໃນ .vue ບໍ່ຕ້ອງແກ້
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
                '**/vite-tailwind-admin-2026/**',
            ],
        },
    },
});
