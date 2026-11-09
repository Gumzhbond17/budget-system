# Solar Expense Tracking API

ອ່ານ `docs/architecture.md` ກ່ອນຂຽນ code ທຸກຄັ້ງ.

## ກົດເກນທີ່ຫ້າມລະເມີດ
- ຈຳນວນເງິນເກັບເປັນ BIGINT minor units ເທົ່ານັ້ນ — ຫ້າມ float/double
- ອັດຕາແລກປ່ຽນ**ທີ່ເກັບ ແລະ snapshot** = "ຈຳນວນສະກຸນຕໍ່ 1 USD" ສະເໝີ
  (`rate_per_usd`, `fx_rate_per_usd`). ໜ້າຈໍຮັບ/ສະແດງເປັນ**ກີບ**
  (1 USD = 22,239 ກີບ, 1 THB = 675.48 ກີບ — `rate_lak`, `fx_rate_lak`) ແລ້ວ
  CurrencyConverter ແປງ. ແຖວ LAK ຄືຄູ່ USD-ກີບ; THB ຄິດຕໍ່ USD ຕອນ lookup
  ດ້ວຍອັດຕາ LAK ຂອງວັນທີລາຍການ
- ການແປງສະກຸນເກີດຂຶ້ນໃນ `App\Domain\Money\CurrencyConverter` ບ່ອນດຽວ —
  ExpenseService ແລະ BudgetItemService ຕ້ອງເອີ້ນຜ່ານມັນ ຫ້າມຄິດເອງ
- ໄຟລ໌ຫຼັກຖານໃຊ້ private disk + signed URL ເທົ່ານັ້ນ
- ທຸກ endpoint ຕ້ອງມີ Policy

## Stack
Laravel 13, MySQL 8, Vue 3 + Pinia + Tailwind, Sanctum SPA auth
