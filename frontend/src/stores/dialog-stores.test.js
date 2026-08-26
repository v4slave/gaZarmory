import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useConfirmationStore } from './confirmation.js'
import { useInputDialogStore } from './input-dialog.js'

beforeEach(() => setActivePinia(createPinia()))

describe('confirmation store', () => {
  it('resolves a confirmed action', async () => {
    const store = useConfirmationStore()
    const result = store.ask({ title: 'Удалить?', danger: true })
    expect(store.open).toBe(true)
    expect(store.danger).toBe(true)
    store.finish(true)
    await expect(result).resolves.toBe(true)
    expect(store.open).toBe(false)
  })

  it('cancels the previous dialog when another one opens', async () => {
    const store = useConfirmationStore()
    const first = store.ask({ title: 'Первый' })
    const second = store.ask({ title: 'Второй' })
    await expect(first).resolves.toBe(false)
    store.finish(false)
    await expect(second).resolves.toBe(false)
  })
})

describe('input dialog store', () => {
  it('returns the entered value', async () => {
    const store = useInputDialogStore()
    const result = store.ask({ title: 'Цена', initialValue: 25, inputType: 'number', min: 0 })
    expect(store.initialValue).toBe('25')
    expect(store.min).toBe(0)
    store.finish('30')
    await expect(result).resolves.toBe('30')
  })

  it('returns null on cancel', async () => {
    const store = useInputDialogStore()
    const result = store.ask({ title: 'Название' })
    store.finish(null)
    await expect(result).resolves.toBeNull()
  })
})
