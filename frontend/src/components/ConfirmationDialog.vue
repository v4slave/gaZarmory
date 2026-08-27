<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useLocale } from '../i18n.js'
import { useConfirmationStore } from '../stores/confirmation.js'

const confirmation = useConfirmationStore()
const { t } = useLocale()
const enteredText = ref('')
const cancelButton = ref(null)
const textInput = ref(null)
const dialog = ref(null)
let previousFocus

function cancel() { confirmation.finish(false) }
function approve() {
  if (confirmation.expectedText && enteredText.value !== confirmation.expectedText) return
  confirmation.finish(true)
}
function onKeydown(event) {
  if (event.key !== 'Tab' || !confirmation.open) return
  const focusable = [...dialog.value.querySelectorAll('button:not(:disabled), input:not(:disabled)')]
  if (!focusable.length) return
  const first = focusable[0]
  const last = focusable.at(-1)
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}

watch(() => confirmation.open, async open => {
  if (open) {
    previousFocus = document.activeElement
    enteredText.value = ''
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    ;(textInput.value ?? cancelButton.value)?.focus()
  } else {
    document.removeEventListener('keydown', onKeydown)
    previousFocus?.focus?.()
  }
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="confirmation.open" class="confirmation-backdrop">
      <section ref="dialog" class="confirmation-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirmation-title" aria-describedby="confirmation-description">
        <header>
          <span aria-hidden="true">{{ confirmation.danger ? '!' : '?' }}</span>
          <h2 id="confirmation-title">{{ t(confirmation.title) }}</h2>
        </header>
        <p id="confirmation-description">{{ t(confirmation.message) }}</p>
        <label v-if="confirmation.expectedText">
          {{ t('Для подтверждения введите') }} <strong>{{ confirmation.expectedText }}</strong>
          <input ref="textInput" v-model="enteredText" autocomplete="off" @keydown.enter.prevent="approve">
        </label>
        <footer>
          <button ref="cancelButton" type="button" @click="cancel">{{ t(confirmation.cancelLabel) }}</button>
          <button type="button" :class="confirmation.danger ? 'danger' : 'primary'" :disabled="Boolean(confirmation.expectedText) && enteredText !== confirmation.expectedText" @click="approve">{{ t(confirmation.confirmLabel) }}</button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.confirmation-backdrop{position:fixed;z-index:1000;inset:0;display:grid;place-items:center;padding:16px;background:rgba(0,0,0,.76);backdrop-filter:blur(4px)}
.confirmation-dialog{width:min(520px,100%);padding:24px;border:1px solid rgba(201,134,38,.48);background:#100b08;box-shadow:0 24px 80px rgba(0,0,0,.7)}
header{display:flex;align-items:center;gap:12px}header span{display:grid;place-items:center;width:34px;height:34px;border:1px solid #a84b3f;border-radius:50%;color:#e68b7e}h2{margin:0;color:var(--cream)}p{margin:18px 0;color:var(--muted);line-height:1.6}label{display:grid;gap:8px;color:var(--muted);font-size:.78rem}label strong{color:var(--cream)}input{width:100%}footer{display:flex;justify-content:flex-end;gap:8px;margin-top:22px}
</style>
