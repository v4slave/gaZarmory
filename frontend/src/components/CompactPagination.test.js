import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CompactPagination from './CompactPagination.vue'

describe('CompactPagination', () => {
  it('keeps large page ranges compact', () => {
    const wrapper = mount(CompactPagination, { props: { page: 7, pages: 24 } })
    expect(wrapper.text()).toContain('1…678…24')
    expect(wrapper.findAll('button').length).toBeLessThanOrEqual(7)
  })
})
