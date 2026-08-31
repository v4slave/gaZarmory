import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppModal from './AppModal.vue'

afterEach(() => { document.body.innerHTML = ''; vi.restoreAllMocks() })

describe('AppModal', () => {
  it('exposes dialog semantics and closes with Escape', async () => {
    const wrapper = mount(AppModal, { attachTo: document.body, props: { open: true, title: 'Test dialog' }, slots: { default: '<button>Action</button>' } })
    expect(document.querySelector('[role="dialog"]')?.getAttribute('aria-modal')).toBe('true')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('shows an in-app confirmation before dismissing dirty content', async () => {
    const wrapper = mount(AppModal, { attachTo: document.body, props: { open: true, dirty: true }, slots: { default: '<input>' } })
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(document.querySelector('[role="alertdialog"]')).not.toBeNull()
    expect(wrapper.emitted('close')).toBeUndefined()
    document.querySelector('[role="alertdialog"] .danger').click()
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })
})
