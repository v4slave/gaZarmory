<script setup>
/* eslint-disable vue/no-deprecated-slot-attribute -- GearSlot exposes a slot-name prop, not a deprecated DOM slot attribute. */
import {
  computed,
  reactive,
  ref,
  watch,
} from "vue";
import { useRoute } from "vue-router";
import { api } from "../api.js";
import StatCard from "../components/StatCard.vue";
import GoldAmount from "../components/GoldAmount.vue";
import PlayerAvatar from "../components/PlayerAvatar.vue";
import AppModal from "../components/AppModal.vue";
import CompactPagination from "../components/CompactPagination.vue";
import GearSlot from "../components/GearSlot.vue";
import { useAuthStore } from "../stores/auth.js";
import {
  apiErrorMessage,
  useNotificationsStore,
} from "../stores/notifications.js";
import { formatDate, formatDecimal, formatInteger } from "../utils/format.js";

const route = useRoute();
const player = ref(null);
const error = ref("");
const auth = useAuthStore();
const showEditor = ref(false);
const savingProfile = ref(false);
const nickname = ref("");
const selectedClass = ref("");
const gearScore = ref(0);
const archaGearUrl = ref("");
const notifications = useNotificationsStore();
const labels = {
  melee: "Милик",
  archer: "Лучник",
  mage: "Маг",
  healer: "Хил",
  bard: "Бард",
  tank: "Танк",
};
const assetLabels = {
  has_ship: "Корабль",
  has_tank: "Танк",
  has_fuchsias: "Фуксория",
  has_clouds: "Облачко",
  has_machaon: "Махаон",
  has_tare: "Таре",
  has_deer: "Олень",
  has_invulnerable_pet: "Пет на неуяз",
  has_shield_swap: "Щит на свап",
  has_flippers: "Ласты",
};
const assetImages = {
  has_ship: "/images/profile-assets/ship.png",
  has_tank: "/images/profile-assets/tank.png",
  has_fuchsias: "/images/profile-assets/fuchsoria.png",
  has_clouds: "/images/profile-assets/cloud.png",
  has_machaon: "/images/profile-assets/machaon.png",
  has_tare: "/images/profile-assets/tare.png",
  has_deer: "/images/profile-assets/deer.png",
  has_invulnerable_pet: "/images/profile-assets/invulnerable-pet.png",
  has_shield_swap: "/images/profile-assets/shield-swap.png",
  has_flippers: "/images/profile-assets/flippers.png",
};
const assetImagePositions = {
  has_ship: "30%",
  has_tank: "28%",
  has_fuchsias: "28%",
  has_clouds: "30%",
  has_machaon: "30%",
  has_tare: "27%",
  has_deer: "25%",
  has_invulnerable_pet: "32%",
  has_shield_swap: "38%",
  has_flippers: "42%",
};
const assets = reactive(
  Object.fromEntries(Object.keys(assetLabels).map((key) => [key, false])),
);
const activities = ref([]);
const activitiesPage = ref(1);
const activitiesPages = ref(1);
const activitiesLoading = ref(false);
const statistics = computed(() => player.value?.statistics ?? {});
const earnings = ref([]);
const earningsPage = ref(1);
const earningsPages = ref(1);
const earningsLoading = ref(false);
const earningStatus = {
  pending: "Ожидается",
  paid: "Выплачено",
  cancelled: "Отменено",
};
const isOwnProfile = computed(
  () => Number(auth.user?.player?.id) === Number(player.value?.id),
);
const isDeveloper = computed(() =>
  (auth.user?.roles ?? [auth.user?.role]).includes("developer"),
);
const canEditProfile = computed(() => isOwnProfile.value || isDeveloper.value);
const gearDelta = computed(() =>
  player.value?.previous_gear_score === null ||
  player.value?.previous_gear_score === undefined
    ? null
    : Number(player.value.gear_score) -
      Number(player.value.previous_gear_score),
);
const gearDeltaText = computed(() =>
  gearDelta.value === null
    ? "Изменений пока нет"
    : `${gearDelta.value >= 0 ? "+" : "−"}${formatInteger(Math.abs(gearDelta.value))} с прошлого обновления`,
);
const gearBySlot = computed(() =>
  Object.fromEntries(
    (player.value?.archa_gear_items ?? []).map((item) => [item.slot, item]),
  ),
);
const armorSlots = [
  "Голова",
  "Нагрудник",
  "Пояс",
  "Наручи",
  "Перчатки",
  "Плащ",
  "Поножи",
  "Обувь",
  "Бельё",
];
const jewelrySlots = [
  "Ожерелье",
  "Серьга 1",
  "Серьга 2",
  "Кольцо 1",
  "Кольцо 2",
];
const weaponSlots = [
  "Основное оружие",
  "Левая рука",
  "Лук",
  "Музыкальный инструмент",
];
const topGearSlots = ["Костюм"];

function syncEditor() {
  nickname.value = player.value.nickname;
  selectedClass.value = player.value.class;
  gearScore.value = Number(player.value.gear_score ?? 0);
  archaGearUrl.value = player.value.archa_gear_url ?? "";
  Object.keys(assetLabels).forEach((key) => {
    assets[key] = Boolean(player.value[key]);
  });
}
async function loadActivities(id, page = 1) {
  activitiesLoading.value = true;
  try {
    const { data } = await api.get(`/api/players/${id}/activities`, {
      params: { page, per_page: 6 },
    });
    activities.value = data.data;
    activitiesPage.value = data.current_page;
    activitiesPages.value = data.last_page;
  } finally {
    activitiesLoading.value = false;
  }
}
async function loadEarnings(id, page = 1) {
  earningsLoading.value = true;
  try {
    const { data } = await api.get(`/api/players/${id}/earnings`, {
      params: { page, per_page: 10 },
    });
    earnings.value = data.data;
    earningsPage.value = data.current_page;
    earningsPages.value = data.last_page;
  } finally {
    earningsLoading.value = false;
  }
}
async function loadPlayer(id) {
  player.value = null;
  activities.value = [];
  earnings.value = [];
  error.value = "";
  try {
    player.value = (await api.get(`/api/players/${id}`)).data;
    syncEditor();
    await Promise.all([loadActivities(id), loadEarnings(id)]);
  } catch (e) {
    error.value = e.response?.data?.message ?? "Не удалось загрузить профиль.";
  }
}
watch(
  () => route.params.id,
  (id) => loadPlayer(id),
  { immediate: true },
);
function openEditor() {
  syncEditor();
  error.value = "";
  showEditor.value = true;
}
async function importGear() {
  if (savingProfile.value) return;
  if (!archaGearUrl.value) {
    error.value = "Вставьте ссылку на билд archa.ge.";
    return;
  }
  savingProfile.value = true;
  error.value = "";
  try {
    const endpoint = isOwnProfile.value
      ? "/api/me/player/archa-gear"
      : `/api/players/${player.value.id}/archa-gear`;
    player.value = (
      await api.post(endpoint, { archa_gear_url: archaGearUrl.value })
    ).data;
    syncEditor();
    notifications.success("Экипировка импортирована с archa.ge.");
  } catch (e) {
    error.value = apiErrorMessage(e, "Не удалось импортировать экипировку.");
    notifications.error(error.value);
  } finally {
    savingProfile.value = false;
  }
}
async function saveProfile() {
  savingProfile.value = true;
  error.value = "";
  try {
    if (!isOwnProfile.value && isDeveloper.value) {
      await api.patch(`/api/players/${player.value.id}/profile`, {
        nickname: nickname.value,
        class: selectedClass.value,
        gear_score: Number(gearScore.value),
        ...assets,
      });
    } else {
      const requests = [];
      if (nickname.value !== player.value.nickname)
        requests.push(
          api.patch("/api/me/player/nickname", { nickname: nickname.value }),
        );
      if (selectedClass.value !== player.value.class)
        requests.push(
          api.patch("/api/me/player/class", { class: selectedClass.value }),
        );
      requests.push(
        api.patch("/api/me/player/profile", {
          gear_score: Number(gearScore.value),
          ...assets,
        }),
      );
      await Promise.all(requests);
      await auth.fetchMe();
    }
    player.value = (await api.get(`/api/players/${route.params.id}`)).data;
    showEditor.value = false;
    notifications.success("Профиль персонажа сохранён.");
  } catch (e) {
    error.value = apiErrorMessage(e, "Не удалось сохранить профиль.");
    notifications.error(error.value);
  } finally {
    savingProfile.value = false;
  }
}
</script>

<template>
  <section v-if="player" class="player-profile-page">
    <p v-if="error" class="notice error">{{ error }}</p>
    <div class="profile-showcase">
      <div class="profile-showcase-identity">
        <PlayerAvatar :player="player" size="hero" />
        <div>
          <p class="eyebrow">ПРОФИЛЬ ИГРОКА</p>
          <h1>
            {{ player.nickname }}
            <span :class="['class-tag', `class-${player.class}`]">{{
              labels[player.class]
            }}</span>
          </h1>
          <p>{{ player.group?.name ?? "Сольники" }}</p>
          <a
            v-if="player.user?.discord_id"
            class="profile-discord-card"
            :href="`https://discord.com/users/${player.user.discord_id}`"
            target="_blank"
            rel="noopener noreferrer"
            :title="`Открыть личные сообщения с ${player.nickname} в Discord`"
            ><i aria-hidden="true"
              ><svg viewBox="0 0 24 24">
                <path
                  fill="currentColor"
                  d="M19.5 5.3A16.3 16.3 0 0 0 15.4 4l-.5 1a15 15 0 0 0-5.8 0l-.5-1a16.3 16.3 0 0 0-4.1 1.3C1.9 9.1 1.2 12.8 1.5 16.4a16.8 16.8 0 0 0 5 2.5l1.2-1.7-1.8-.9.4-.3c3.5 1.6 7.9 1.6 11.4 0l.4.3-1.8.9 1.2 1.7a16.8 16.8 0 0 0 5-2.5c.4-4.2-.8-7.8-3-11.1ZM8.7 14.6c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Zm6.6 0c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Z"
                /></svg></i
            ><span
              ><small>Discord</small
              ><b>{{
                player.user.discord_display_name || player.user.discord_username
              }}</b></span
            ></a
          ><span v-else-if="player.user" class="profile-discord-card"
            ><i aria-hidden="true"
              ><svg viewBox="0 0 24 24">
                <path
                  fill="currentColor"
                  d="M19.5 5.3A16.3 16.3 0 0 0 15.4 4l-.5 1a15 15 0 0 0-5.8 0l-.5-1a16.3 16.3 0 0 0-4.1 1.3C1.9 9.1 1.2 12.8 1.5 16.4a16.8 16.8 0 0 0 5 2.5l1.2-1.7-1.8-.9.4-.3c3.5 1.6 7.9 1.6 11.4 0l.4.3-1.8.9 1.2 1.7a16.8 16.8 0 0 0 5-2.5c.4-4.2-.8-7.8-3-11.1ZM8.7 14.6c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Zm6.6 0c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Z"
                /></svg></i
            ><span
              ><small>Discord</small
              ><b>{{
                player.user.discord_display_name || player.user.discord_username
              }}</b></span
            ></span
          >
        </div>
      </div>
      <button
        v-if="canEditProfile"
        class="secondary profile-edit-button"
        @click="openEditor"
      >
        Редактировать профиль
      </button>
      <div class="profile-stats">
        <article class="stat-card profile-gear-stat">
          <span>ГС</span
          ><strong>{{ formatInteger(player.gear_score ?? 0) }}</strong
          ><small
            :class="
              gearDelta === null ? '' : gearDelta >= 0 ? 'positive' : 'negative'
            "
            >{{ gearDeltaText }}</small
          >
        </article>
        <StatCard
          label="Праймы · 30 дней"
          :value="statistics.primes_count ?? 0"
        /><StatCard
          label="Посещение праймов · 30 дней"
          :value="`${formatDecimal(statistics.prime_attendance_percentage ?? 0)}%`"
        /><StatCard
          label="Выплачено"
          :value="formatInteger(statistics.paid_gold ?? 0)"
          gold
        /><StatCard
          label="Ожидается"
          :value="formatInteger(statistics.pending_gold ?? 0)"
          gold
        />
      </div>
    </div>
    <div class="profile-equipment-row">
    <div class="panel player-assets-panel">
      <div class="panel-title">
        <h2>Персоналки</h2>
        <span class="muted"
          >{{ Object.keys(assetLabels).filter((key) => player[key]).length }} /
          {{ Object.keys(assetLabels).length }}</span
        >
      </div>
      <div class="player-assets">
        <span
          v-for="(label, key) in assetLabels"
          :key="key"
          :class="{ owned: player[key] }"
          ><img
            class="profile-asset-image"
            :src="assetImages[key]"
            :alt="label"
            loading="lazy"
            :style="{ objectPosition: `${assetImagePositions[key]} center` }"
          /><span class="profile-asset-copy"
            ><i aria-hidden="true">{{ player[key] ? "✓" : "×" }}</i
            ><span
              ><b>{{ label }}</b
              ><small>{{ player[key] ? "В наличии" : "Нет" }}</small></span
            ></span
          ></span
        >
      </div>
    </div>
    <div v-if="player.archa_gear_items?.length" class="panel archa-gear-panel">
      <div class="panel-title">
        <div>
          <p class="eyebrow">ARCHA.GE</p>
          <h2>Экипировка персонажа</h2>
        </div>
        <a
          :href="player.archa_gear_url"
          target="_blank"
          rel="noopener noreferrer"
          >Открыть билд ↗</a
        >
      </div>
      <div class="game-gear-layout">
        <div class="game-gear-top">
          <GearSlot
            v-for="slot in topGearSlots"
            :key="slot"
            :slot="slot"
            :item="gearBySlot[slot]"
          />
        </div>
        <div class="game-gear-column game-gear-armor">
          <GearSlot
            v-for="slot in armorSlots"
            :key="slot"
            :slot="slot"
            :item="gearBySlot[slot]"
          />
        </div>
        <div class="game-gear-character">
          <PlayerAvatar :player="player" size="hero" /><strong>{{
            player.nickname
          }}</strong
          ><span>{{ labels[player.class] }}</span>
        </div>
        <div class="game-gear-column game-gear-right">
          <GearSlot
            v-for="slot in [...jewelrySlots, ...weaponSlots]"
            :key="slot"
            :slot="slot"
            :item="gearBySlot[slot]"
          />
        </div>
      </div>
    </div>
    </div>
    <div class="split-grid profile profile-history-grid">
      <div class="panel">
        <h2>История начислений</h2>
        <div v-if="earnings.length" class="earning-list">
          <div v-for="item in earnings" :key="item.id">
            <span class="profile-activity-icon"
              ><img
                v-if="item.activity?.definition?.icon_url"
                :src="item.activity.definition.icon_url"
                :alt="item.activity.definition.name"
              /><i v-else>◆</i></span
            ><span class="profile-activity-details"
              ><strong>{{ item.activity?.definition?.name }}</strong
              ><small
                >Прайм · {{ formatDate(item.activity?.occurred_at) }}</small
              ></span
            ><b><GoldAmount :value="formatInteger(item.player_share)" /></b
            ><span
              :class="[
                'import-status',
                item.status === 'paid' ? 'confirmed' : 'draft',
              ]"
              >{{ earningStatus[item.status] }}</span
            >
          </div>
        </div>
        <p v-else class="empty">Начислений за праймы пока нет.</p>
        <CompactPagination
          :page="earningsPage"
          :pages="earningsPages"
          :disabled="earningsLoading"
          label="Страницы начислений"
          @change="loadEarnings(player.id, $event)"
        />
      </div>
      <div class="panel">
        <h2>Последние посещения</h2>
        <div v-if="activities.length" class="visit-list">
          <div v-for="item in activities" :key="item.id">
            <span class="profile-activity-icon"
              ><img
                v-if="item.definition?.icon_url"
                :src="item.definition.icon_url"
                :alt="item.definition.name"
              /><i v-else>◆</i></span
            ><strong>{{ item.definition?.name }}</strong
            ><span>{{ formatDate(item.occurred_at) }}</span>
          </div>
        </div>
        <p v-else class="empty">Посещений праймов пока нет.</p>
        <CompactPagination
          :page="activitiesPage"
          :pages="activitiesPages"
          :disabled="activitiesLoading"
          label="Страницы посещений"
          @change="loadActivities(player.id, $event)"
        />
      </div>
    </div>
    <AppModal
      :open="showEditor"
      title="Редактировать профиль"
      @close="showEditor = false"
      ><form class="form-card profile-edit-modal" @submit.prevent="saveProfile">
        <header class="profile-edit-modal-head">
          <div>
            <span>✎</span>
            <h2>Редактировать профиль</h2>
          </div>
          <button
            type="button"
            aria-label="Закрыть"
            @click="showEditor = false"
          >
            ×
          </button>
        </header>
        <div class="profile-edit-section">
          <div class="profile-edit-section-title">
            <span>▣</span><strong>Персонаж</strong>
          </div>
          <label
            >Никнейм<input
              v-model.trim="nickname"
              maxlength="18"
              pattern="[A-Za-zА-Яа-яЁё]+"
              title="Только русские или латинские буквы, без пробелов, цифр и специальных символов"
              required /></label
          ><label
            >Класс<select v-model="selectedClass" required>
              <option
                v-for="(label, value) in labels"
                :key="value"
                :value="value"
              >
                {{ label }}
              </option>
            </select></label
          ><label
            >ГС<input
              v-model.number="gearScore"
              type="number"
              min="0"
              max="100000"
              required
          /></label>
        </div>
        <div class="profile-edit-section">
          <div class="profile-edit-section-title">
            <span>↗</span><strong>Экипировка archa.ge</strong>
          </div>
          <label
            >Ссылка на билд<input
              v-model.trim="archaGearUrl"
              type="url"
              placeholder="https://archa.ge/?u=…&bid=…"
          /></label>
          <p class="archa-import-help">
            Вставьте ссылку на билд и нажмите кнопку импорта. Armory сам
            загрузит экипировку с archa.ge.
          </p>
          <button
            type="button"
            class="secondary archa-bookmarklet"
            :disabled="savingProfile"
            @click="importGear"
          >
            {{ savingProfile ? "Импортируем…" : "Импорт в Armory" }}
          </button>
          <a
            v-if="archaGearUrl"
            class="archa-open-build"
            :href="archaGearUrl"
            target="_blank"
            rel="noopener noreferrer"
            >Открыть сохранённый билд ↗</a
          >
        </div>
        <div class="profile-edit-section">
          <div class="profile-edit-section-title">
            <span>✓</span><strong>Персоналки</strong>
          </div>
          <div class="profile-asset-editor">
            <label v-for="(label, key) in assetLabels" :key="key"
              ><input v-model="assets[key]" type="checkbox" /><span>{{
                label
              }}</span></label
            >
          </div>
        </div>
        <p v-if="error" class="notice error">{{ error }}</p>
        <div class="form-actions">
          <button type="button" class="secondary" @click="showEditor = false">
            Отмена</button
          ><button class="primary" :disabled="savingProfile || !nickname">
            {{ savingProfile ? "Сохранение…" : "Сохранить" }}
          </button>
        </div>
      </form></AppModal
    >
  </section>
  <section v-else>
    <p :class="error ? 'notice error' : 'empty'">{{ error || "Загрузка…" }}</p>
  </section>
</template>
