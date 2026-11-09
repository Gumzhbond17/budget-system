import {
    AllCommunityModule,
    ModuleRegistry,
    themeQuartz,
} from 'ag-grid-community';
import type { ColDef } from 'ag-grid-community';

/**
 * ຕັ້ງຄ່າ AG Grid ບ່ອນດຽວສຳລັບທັງແອັບ.
 *
 * ໃຊ້ຮຸ່ນ Community (MIT) ລ້ວນໆ — ບໍ່ມີ licence key ແລະ ບໍ່ໄດ້ນຳໃຊ້
 * ຄວາມສາມາດຝັ່ງ Enterprise (Set Filter, ການຈັດກຸ່ມແຖວ, ແຖວຍອດລວມ,
 * Server-Side Row Model). ຖ້າມີໃຜເພີ່ມມັນເຂົ້າມາ grid ຈະເຕືອນຢູ່ console
 * ແລ້ວຄວາມສາມາດນັ້ນຈະບໍ່ເຮັດວຽກ ບໍ່ແມ່ນລົ້ມທັງໜ້າ.
 */
ModuleRegistry.registerModules([AllCommunityModule]);

/**
 * ຮູບແບບຂອງ grid ໃຫ້ກົງກັບຕາຕະລາງເດີມ (ແລະ ກັບ card ທີ່ຫຸ້ມມັນຢູ່).
 *
 * ສີມາຈາກ token ດຽວກັນກັບ `resources/css/app.css` — ຂຽນເປັນຄ່າ hex ຊ້ຳຢູ່ນີ້
 * ເພາະ grid ສີດ CSS ຂອງຕົນເອງເຂົ້າ DOM ກ່ອນ Tailwind ຈະ resolve ຕົວແປ.
 *
 * ໂໝດມືດຕິດຕາມແອັບຜ່ານ `data-ag-theme-mode` ທີ່ ThemeProvider ຕິດໃສ່ <body>
 * — ບໍ່ແມ່ນຜ່ານ `prefers-color-scheme` ຂອງ browser ເພາະຜູ້ໃຊ້ເລືອກເອງໄດ້.
 */
export const gridTheme = themeQuartz
    .withParams({
        // ຟອນຕ້ອງມີ Phetsarath ຕໍ່ທ້າຍ ບໍ່ດັ່ງນັ້ນສະຫຼະລອຍ ແລະ ວັນນະຍຸດລາວ
        // ຈະ render ຜິດຕຳແໜ່ງຢູ່ໃນ grid (docs/architecture.md ພາກ 6)
        fontFamily: "'Google Sans', 'Phetsarath OT', sans-serif",
        fontSize: '14px',
        headerFontSize: '12px',
        headerFontWeight: 500,
        headerHeight: 44,
        rowHeight: 52,
        borderRadius: '8px',

        // card ທີ່ຫຸ້ມຢູ່ມີຂອບ ແລະ ມຸມມົນຂອງຕົນເອງແລ້ວ — ຖ້າ grid ຕີຂອບອີກຊັ້ນ
        // ຈະເຫັນເປັນສອງເສັ້ນຊ້ອນກັນ
        wrapperBorder: false,
        wrapperBorderRadius: '0px',
        columnBorder: false,
        headerColumnBorder: false,
    })
    .withParams(
        {
            backgroundColor: '#ffffff',
            foregroundColor: '#344054', // gray-700
            textColor: '#344054',
            subtleTextColor: '#667085', // gray-500
            headerBackgroundColor: '#ffffff',
            headerTextColor: '#667085',
            chromeBackgroundColor: '#ffffff',
            borderColor: '#e4e7ec', // gray-200
            rowBorder: { color: '#f2f4f7' }, // gray-100 — ຄືກັບ divide-gray-100 ເດີມ
            rowHoverColor: '#f9fafb', // gray-50
            oddRowBackgroundColor: 'transparent',
            accentColor: '#465fff', // brand-500
            menuBackgroundColor: '#ffffff',
            menuTextColor: '#344054',
            browserColorScheme: 'light',
        },
        'light',
    )
    .withParams(
        {
            // card ໃນໂໝດມືດເປັນ `bg-white/[0.03]` ຊ້ອນເທິງ gray-900 — grid ຕ້ອງ
            // ການສີທຶບ ຈຶ່ງໃຊ້ gray-dark ທີ່ເປັນຜົນລວມສາຍຕາອັນດຽວກັນ
            backgroundColor: '#1a2231',
            foregroundColor: '#d0d5dd', // gray-300
            textColor: '#d0d5dd',
            subtleTextColor: '#98a2b3', // gray-400
            headerBackgroundColor: '#1a2231',
            headerTextColor: '#98a2b3',
            chromeBackgroundColor: '#1a2231',
            borderColor: '#1d2939', // gray-800
            rowBorder: { color: '#1d2939' },
            rowHoverColor: 'rgba(255, 255, 255, 0.05)',
            oddRowBackgroundColor: 'transparent',
            accentColor: '#465fff',
            menuBackgroundColor: '#101828', // gray-900
            menuTextColor: '#d0d5dd',
            browserColorScheme: 'dark',
        },
        'dark',
    );

/**
 * ຄ່າເລີ່ມຕົ້ນຂອງທຸກຖັນ.
 *
 * ເປີດ `filter` ແລະ `floatingFilter` ໄວ້ໝົດ: ນີ້ຄືເຫດຜົນທີ່ຍ້າຍມາໃຊ້ AG Grid
 * ຕັ້ງແຕ່ຕົ້ນ. ຖັນທີ່ບໍ່ຄວນມີ (ເຊັ່ນ ຖັນປຸ່ມຈັດການ) ປິດເປັນລາຍຖັນເອົາ.
 */
export const defaultColDef: ColDef = {
    sortable: true,
    filter: true,
    floatingFilter: true,
    resizable: true,
    // ຫົວຖັນລາວຍາວກວ່າອັງກິດເກືອບເທົ່າຕົວ — ຕັດຄຳແທນທີ່ຈະໃຫ້ລົ້ນອອກ
    wrapHeaderText: true,
    autoHeaderHeight: true,
    minWidth: 90,
};

/** ຖັນປຸ່ມຈັດການ: ຮຽງ/ກັ່ນຕອງ/ຍ້າຍບໍ່ໄດ້ ເພາະບໍ່ໄດ້ຖືຂໍ້ມູນຫຍັງ */
export const actionColDef: ColDef = {
    sortable: false,
    filter: false,
    floatingFilter: false,
    resizable: false,
    suppressMovable: true,
    suppressHeaderMenuButton: true,
    cellClass: 'ag-cell-actions',
};

/**
 * ຄຳແປຂອງ grid ເອງ (ປຸ່ມກັ່ນຕອງ, ການແບ່ງໜ້າ, ຂໍ້ຄວາມເມື່ອບໍ່ມີແຖວ).
 *
 * ດຶງຜ່ານ `t()` ບໍ່ແມ່ນຂຽນ map ໄວ້ຢູ່ນີ້ ເພື່ອໃຫ້ຄຳແປຢູ່ບ່ອນດຽວກັນກັບສ່ວນ
 * ອື່ນຂອງແອັບ ແລະ ຖືກ LocaleFilesTest ກວດວ່າມີຄົບທັງສອງພາສາ.
 */
export function gridLocaleText(
    t: (key: string) => string,
): Record<string, string> {
    const keys = [
        // ຕົວກັ່ນຕອງ
        'filterOoo',
        'applyFilter',
        'resetFilter',
        'clearFilter',
        'cancelFilter',
        'contains',
        'notContains',
        'equals',
        'notEqual',
        'startsWith',
        'endsWith',
        'blank',
        'notBlank',
        'lessThan',
        'lessThanOrEqual',
        'greaterThan',
        'greaterThanOrEqual',
        'inRange',
        'inRangeStart',
        'inRangeEnd',
        'before',
        'after',
        'andCondition',
        'orCondition',
        // ເມນູຫົວຖັນ
        'sortAscending',
        'sortDescending',
        'sortUnSort',
        'pinColumn',
        'noPin',
        'pinLeft',
        'pinRight',
        'autosizeThisColumn',
        'autosizeAllColumns',
        'resetColumns',
        'columnFilter',
        'columnMenu',
        // ການແບ່ງໜ້າ
        'page',
        'pageSizeSelectorLabel',
        'to',
        'of',
        'firstPage',
        'previousPage',
        'nextPage',
        'lastPage',
        // ສະຖານະ
        'noRowsToShow',
        'loadingOoo',
    ];

    return Object.fromEntries(keys.map((key) => [key, t(`grid.${key}`)]));
}
