<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useLocale } from '../i18n.js'

const props = defineProps({
  open: Boolean,
  title: { type: String, default: '' },
  closeLabel: { type: String, default: 'Закрыть' },
  dismissible: { type: Boolean, default: true },
  dirty: Boolean,
})
const emit = defineEmits(['close'])
const dialog = ref(null)
const discardDialog = ref(null)
const confirmingDiscard = ref(false)
const { t } = useLocale()
let previousFocus

function requestClose() {
  if (!props.dismissible) return
  if (props.dirty) {
    confirmingDiscard.value = true
    nextTick(() => discardDialog.value?.querySelector('button')?.focus())
    return
  }
  emit('close')
}
function discardChanges() {
  confirmingDiscard.value = false
  emit('close')
}
function onKeydown(event) {
  if (!props.open) return
  if (confirmingDiscard.value && event.key === 'Escape') { event.preventDefault(); confirmingDiscard.value = false; return }
  if (event.key === 'Escape') { event.preventDefault(); requestClose(); return }
  if (event.key !== 'Tab' || !dialog.value) return
  const focusable = [...dialog.value.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')]
  if (!focusable.length) { event.preventDefault(); dialog.value.focus(); return }
  const first = focusable[0]; const last = focusable.at(-1)
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
async function activate() {
  previousFocus = document.activeElement
  document.body.classList.add('modal-open')
  await nextTick()
  dialog.value?.querySelector('[autofocus],input,select,textarea,button')?.focus()
}
function deactivate() {
  confirmingDiscard.value = false
  document.body.classList.remove('modal-open')
  previousFocus?.focus?.()
}
watch(() => props.open, value => value ? activate() : deactivate())
onMounted(() => { document.addEventListener('keydown', onKeydown); if (props.open) activate() })
onBeforeUnmount(() => { document.removeEventListener('keydown', onKeydown); deactivate() })
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="open" class="modal app-modal" @mousedown.self="requestClose">
        <div ref="dialog" class="app-modal-dialog" role="dialog" aria-modal="true" :aria-label="title || t(closeLabel)" tabindex="-1">
          <slot />
        </div>
        <Transition name="modal-fade">
          <div v-if="confirmingDiscard" class="app-modal-confirm-layer" @mousedown.self="confirmingDiscard=false">
            <section ref="discardDialog" class="app-modal-confirm" role="alertdialog" aria-modal="true" :aria-label="t('Закрыть без сохранения изменений?')">
              <h3>{{ t('Закрыть без сохранения изменений?') }}</h3>
              <p>{{ t('Несохранённые изменения будут потеряны.') }}</p>
              <div class="form-actions"><button type="button" @click="confirmingDiscard=false">{{ t('Остаться') }}</button><button type="button" class="danger" @click="discardChanges">{{ t('Закрыть') }}</button></div>
            </section>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
