<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'

const auth = useAuthStore()
const posts = ref([]), loading = ref(true), loadingMore = ref(false), saving = ref(false), error = ref('')
const nextPage = ref(null)
const search = ref(''), kind = ref(''), sort = ref('new'), favorites = ref(false)
const showAdd = ref(false), addMode = ref('url'), active = ref(null), selectedFile = ref(null)
const form = ref({ title: '', description: '', url: '' })
const titleLoading = ref(false), autoTitle = ref('')
let searchTimer
let metadataTimer
let loadSequence = 0

const canDelete = post => post.user_id === auth.user?.id || auth.canAdmin
const mediaSrc = post => post.provider === 'upload' ? `/api/media/${post.id}/file` : post.source_url
const previewSrc = post => post.thumbnail_url || (post.kind === 'image' ? mediaSrc(post) : '')
const authorName = post => post.author?.player?.nickname || post.author?.discord_display_name || post.author?.discord_username || 'Участник'
const providerName = computed(() => ({ youtube:'YouTube', rutube:'Rutube', vimeo:'Vimeo', direct:'Ссылка', upload:'Файл' }))

async function load(append = false) {
  const sequence = ++loadSequence
  append ? loadingMore.value = true : loading.value = true; error.value = ''
  try {
    const { data } = await api.get('/api/media', { params: { search: search.value || undefined, kind: kind.value || undefined, sort: sort.value, favorites: favorites.value ? 1 : undefined, page: append ? nextPage.value : 1 } })
    if (sequence !== loadSequence) return
    posts.value = append
      ? [...new Map([...posts.value, ...data.data].map(post => [post.id, post])).values()]
      : data.data
    nextPage.value = data.next_page_url ? data.current_page + 1 : null
  } catch (e) { if (sequence === loadSequence) error.value = e.response?.data?.message || 'Не удалось загрузить медиатеку.' }
  finally { if (sequence === loadSequence) { loading.value = false; loadingMore.value = false } }
}
watch([kind, sort, favorites], () => load(false))
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(false), 300) })
onMounted(() => load(false))
watch(() => form.value.url, url => {
  clearTimeout(metadataTimer)
  if (addMode.value !== 'url' || !/^https?:\/\//i.test(url || '')) return
  metadataTimer = setTimeout(() => loadMetadata(url), 450)
})

async function loadMetadata(url) {
  titleLoading.value = true
  try {
    const { data } = await api.post('/api/media/metadata', { url })
    if (form.value.url === url && (!form.value.title || form.value.title === autoTitle.value)) {
      autoTitle.value = data.title; form.value.title = data.title
    }
  } catch {} finally { if (form.value.url === url) titleLoading.value = false }
}

function openAdd() { showAdd.value = true; error.value = ''; autoTitle.value = ''; titleLoading.value = false; form.value = { title:'', description:'', url:'' }; selectedFile.value = null }
function chooseFile(event) { selectedFile.value = event.target.files?.[0] || null; if (selectedFile.value && !form.value.title) form.value.title = selectedFile.value.name.replace(/\.[^.]+$/, '') }
async function submit() {
  saving.value = true; error.value = ''
  try {
    const body = new FormData(); body.append('title', form.value.title); if (form.value.description) body.append('description', form.value.description)
    if (addMode.value === 'file') body.append('file', selectedFile.value); else body.append('url', form.value.url)
    await api.post('/api/media', body); showAdd.value = false; await load()
  } catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'Не удалось добавить публикацию.' }
  finally { saving.value = false }
}
async function react(post, type) {
  const field = type === 'like' ? 'liked_by_me' : 'favorite_by_me', count = type === 'like' ? 'likes_count' : 'favorites_count'
  const { data } = await api.post(`/api/media/${post.id}/reaction`, { type }); post[field] = data.active; post[count] += data.active ? 1 : -1
}
async function remove(post) { if (!confirm(`Удалить «${post.title}»?`)) return; await api.delete(`/api/media/${post.id}`); if (active.value?.id === post.id) active.value = null; posts.value = posts.value.filter(item => item.id !== post.id) }
</script>

<template>
  <section class="media-page">
    <div class="page-heading media-heading"><div><p class="eyebrow">Творчество гильдии</p><h1>Контент</h1><p class="muted">Видео, моменты, гайды и мемы участников — всё в одном месте.</p></div><button class="primary media-add" @click="openAdd">＋ Добавить</button></div>
    <div class="media-toolbar">
      <label class="media-search"><span>⌕</span><input v-model.trim="search" placeholder="Поиск по названию…"></label>
      <div class="media-filters"><button :class="{active:!kind&&!favorites}" @click="kind='';favorites=false">Все</button><button :class="{active:kind==='video'}" @click="kind='video';favorites=false">Видео</button><button :class="{active:kind==='image'}" @click="kind='image';favorites=false">Изображения</button><button :class="{active:favorites}" @click="favorites=!favorites">★ Избранное</button></div>
      <div class="media-sort"><button :class="{active:sort==='new'}" @click="sort='new'">◷ Новые</button><button :class="{active:sort==='popular'}" @click="sort='popular'">♨ Популярные</button></div>
    </div>
    <p v-if="error&&!showAdd" class="notice error">{{ error }}</p>
    <div v-if="loading" class="media-empty">Загружаем медиатеку…</div>
    <div v-else-if="!posts.length" class="media-empty"><b>Здесь пока тихо</b><span>Добавьте первое видео или изображение.</span><button class="primary" @click="openAdd">Добавить публикацию</button></div>
    <div v-else class="media-grid">
      <article v-for="post in posts" :key="post.id" class="media-card">
        <button class="media-preview" @click="active=post">
          <img v-if="previewSrc(post)" :src="previewSrc(post)" :alt="post.title" loading="lazy" decoding="async">
          <span v-else class="media-provider-placeholder">{{ providerName[post.provider] || 'Видео' }}</span>
          <i v-if="post.kind==='video'" class="media-play">▶</i><em>{{ post.kind === 'video' ? '▣' : '▧' }}</em>
        </button>
        <div class="media-card-body"><h2>{{ post.title }}</h2><p v-if="post.description">{{ post.description }}</p><div><strong>{{ authorName(post) }}</strong><time>{{ new Date(post.created_at).toLocaleDateString('ru-RU') }}</time></div></div>
        <footer><button :class="{active:post.liked_by_me}" @click="react(post,'like')">♥ {{ post.likes_count }}</button><button :class="{active:post.favorite_by_me}" @click="react(post,'favorite')">★ {{ post.favorites_count }}</button><button v-if="canDelete(post)" class="media-delete" title="Удалить" @click="remove(post)">×</button></footer>
      </article>
    </div>
    <div v-if="nextPage&&!loading" class="media-load-more"><button :disabled="loadingMore" @click="load(true)">{{ loadingMore ? 'Загружаем…' : 'Показать ещё' }}</button></div>

    <div v-if="showAdd" class="modal media-add-modal" @click.self="showAdd=false">
      <form class="form-card" @submit.prevent="submit"><button class="media-modal-close" type="button" aria-label="Закрыть" @click="showAdd=false">×</button><p class="eyebrow">Новая публикация</p><h2>Добавить в медиатеку</h2>
        <div class="media-mode"><button type="button" :class="{active:addMode==='url'}" @click="addMode='url'"><b>▶</b><span>Ссылка<small>YouTube, Rutube, Vimeo</small></span></button><button type="button" :class="{active:addMode==='file'}" @click="addMode='file'"><b>⇧</b><span>Изображение<small>Файл с устройства</small></span></button></div>
        <label v-if="addMode==='url'">Ссылка на видео или изображение<input v-model.trim="form.url" type="url" placeholder="https://youtube.com/watch?v=…" required></label>
        <label v-else class="media-file">Изображение<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" required @change="chooseFile"><span>{{ selectedFile?.name || 'Выбрать изображение' }}</span><small>До 20 МБ · JPG, PNG, GIF или WebP</small></label>
        <label>Название <small v-if="titleLoading">получаем с платформы…</small><input v-model.trim="form.title" maxlength="160" :placeholder="titleLoading ? 'Получаем название…' : 'Название заполнится автоматически'"></label><label>Описание <small>(необязательно)</small><textarea v-model.trim="form.description" maxlength="2000" rows="3" placeholder="Добавьте немного контекста…"></textarea></label>
        <p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" @click="showAdd=false">Отмена</button><button class="primary" :disabled="saving">{{ saving ? 'Публикуем…' : 'Опубликовать' }}</button></div>
      </form>
    </div>

    <div v-if="active" class="modal media-viewer" @click.self="active=null"><article><button class="media-modal-close" aria-label="Закрыть" @click="active=null">×</button><div class="media-stage"><iframe v-if="active.embed_url" :src="active.embed_url" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe><video v-else-if="active.kind==='video'" :src="mediaSrc(active)" controls autoplay></video><img v-else :src="mediaSrc(active)" :alt="active.title"></div><div class="media-view-info"><div><h2>{{ active.title }}</h2><p v-if="active.description">{{ active.description }}</p><small>{{ authorName(active) }} · {{ new Date(active.created_at).toLocaleDateString('ru-RU') }}</small></div><a v-if="active.source_url&&active.provider!=='direct'" :href="active.source_url" target="_blank" rel="noopener">Открыть на {{ providerName[active.provider] }} ↗</a></div><footer><button :class="{active:active.liked_by_me}" @click="react(active,'like')">♥ {{ active.likes_count }}</button><button :class="{active:active.favorite_by_me}" @click="react(active,'favorite')">★ {{ active.favorites_count }}</button></footer></article></div>
  </section>
</template>
