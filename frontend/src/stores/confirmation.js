import { defineStore } from 'pinia'

let resolveConfirmation

export const useConfirmationStore = defineStore('confirmation', {
  state: () => ({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Подтвердить',
    cancelLabel: 'Отмена',
    danger: false,
    expectedText: '',
  }),
  actions: {
    ask(options) {
      if (resolveConfirmation) resolveConfirmation(false)
      Object.assign(this, {
        open: true,
        title: options.title ?? 'Подтвердите действие',
        message: options.message ?? '',
        confirmLabel: options.confirmLabel ?? 'Подтвердить',
        cancelLabel: options.cancelLabel ?? 'Отмена',
        danger: options.danger ?? false,
        expectedText: options.expectedText ?? '',
      })
      return new Promise(resolve => { resolveConfirmation = resolve })
    },
    finish(confirmed) {
      if (!this.open) return
      this.open = false
      resolveConfirmation?.(confirmed)
      resolveConfirmation = undefined
    },
  },
})
