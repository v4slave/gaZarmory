import { defineStore } from 'pinia'

let resolveInput

export const useInputDialogStore = defineStore('inputDialog', {
  state: () => ({
    open: false,
    title: '',
    message: '',
    label: '',
    initialValue: '',
    inputType: 'text',
    min: null,
    max: null,
    step: null,
    maxLength: null,
    confirmLabel: 'Сохранить',
  }),
  actions: {
    ask(options) {
      if (resolveInput) resolveInput(null)
      Object.assign(this, {
        open: true,
        title: options.title ?? 'Введите значение',
        message: options.message ?? '',
        label: options.label ?? 'Значение',
        initialValue: String(options.initialValue ?? ''),
        inputType: options.inputType ?? 'text',
        min: options.min ?? null,
        max: options.max ?? null,
        step: options.step ?? null,
        maxLength: options.maxLength ?? null,
        confirmLabel: options.confirmLabel ?? 'Сохранить',
      })
      return new Promise(resolve => { resolveInput = resolve })
    },
    finish(value) {
      if (!this.open) return
      this.open = false
      resolveInput?.(value)
      resolveInput = undefined
    },
  },
})
