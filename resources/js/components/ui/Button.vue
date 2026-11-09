<template>
  <!--
    nowrap + shrink-0: ຢູ່ແຖວດຽວກັບຕົວກັ່ນຕອງ flex ເຄີຍບີບປຸ່ມຈົນ
    "ຕັ້ງອັດຕາ" ຕັດເປັນສອງແຖວ
  -->
  <button
    :type="type"
    :class="[
      'inline-flex shrink-0 items-center justify-center whitespace-nowrap font-medium gap-2 rounded-lg transition focus:outline-hidden focus-visible:ring-3 focus-visible:ring-brand-500/20',
      sizeClasses[size],
      variantClasses[variant],
      className,
      { 'cursor-not-allowed opacity-50': disabled },
    ]"
    @click="onClick"
    :disabled="disabled"
  >
    <span v-if="startIcon" class="flex items-center">
      <component :is="startIcon" />
    </span>
    <slot></slot>
    <span v-if="endIcon" class="flex items-center">
      <component :is="endIcon" />
    </span>
  </button>
</template>

<script setup lang="ts">
interface ButtonProps {
  /** compact = ສູງ 40px ເທົ່າຊ່ອງຄົ້ນຫາ/ຕົວກັ່ນຕອງ ທີ່ຢູ່ແຖວດຽວກັນ */
  size?: 'compact' | 'sm' | 'md'
  variant?: 'primary' | 'outline'
  startIcon?: object
  endIcon?: object
  onClick?: () => void
  className?: string
  disabled?: boolean
  /**
   * ຄ່າຕັ້ງຕົ້ນຂອງ HTML ຄື `submit` ບໍ່ແມ່ນ `button` — ປຸ່ມນີ້ຢູ່ໃນ <form>
   * (ເຊັ່ນ "ຍົກເລີກ" ຂອງ FormModal) ຈຶ່ງເຄີຍ submit ຟອມພ້ອມກັບປິດມັນ
   * ຄື ກົດຍົກເລີກແລ້ວລາຍການຖືກບັນທຶກ. ບັງຄັບເປັນ `button` ໄວ້ກ່ອນ.
   */
  type?: 'button' | 'submit' | 'reset'
}

const props = withDefaults(defineProps<ButtonProps>(), {
  size: 'md',
  variant: 'primary',
  className: '',
  disabled: false,
  type: 'button',
})

const sizeClasses = {
  compact: 'h-10 px-4 text-sm',
  sm: 'px-4 py-3 text-sm',
  md: 'px-5 py-3.5 text-sm',
}

const variantClasses = {
  primary: 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300',
  outline:
    'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300',
}

const onClick = () => {
  if (!props.disabled && props.onClick) {
    props.onClick()
  }
}
</script>
