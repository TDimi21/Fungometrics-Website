<script setup>
import { computed, ref } from "vue";
const props = defineProps({
  players: { type: Array, default: () => [] },
  modelValue: { type: Array, default: () => [] },
  label: String,
  positions: { type: Object, default: () => ({}) },
  disabled: Boolean,
});
const emit = defineEmits(["update:modelValue", "update:positions"]);
const open = ref(false),
  search = ref("");
const name = (p) =>
  p?.name?.full ||
  [p?.name?.first, p?.name?.last].filter(Boolean).join(" ") ||
  "Player";
const filtered = computed(() =>
  props.players.filter((p) =>
    name(p).toLowerCase().includes(search.value.toLowerCase()),
  ),
);
function toggle(id) {
  if (props.disabled) return;
  emit(
    "update:modelValue",
    props.modelValue.includes(id)
      ? props.modelValue.filter((v) => v !== id)
      : [...props.modelValue, id].slice(0, 20),
  );
}
function move(index, step) {
  const ids = [...props.modelValue],
    next = index + step;
  if (next < 0 || next >= ids.length) return;
  [ids[index], ids[next]] = [ids[next], ids[index]];
  emit("update:modelValue", ids);
}
</script>
<template>
  <section class="lineup">
    <button
      class="opener"
      :disabled="disabled"
      :aria-expanded="open"
      @click="open = !open"
    >
      {{ label }} <span>{{ modelValue.length }} selected · ＋</span>
    </button>
    <div v-if="open" class="picker">
      <input
        v-model="search"
        aria-label="Search players"
        placeholder="Search roster"
      />
      <p v-if="!players.length">Choose a team with roster players.</p>
      <label v-for="p in filtered" :key="p.id"
        ><input
          type="checkbox"
          :disabled="disabled"
          :checked="modelValue.includes(p.id)"
          @change="toggle(p.id)"
        />{{ name(p) }} <small>#{{ p.shirt_number || "—" }}</small></label
      >
    </div>
    <ol>
      <li v-for="(id, i) in modelValue" :key="id">
        <span
          >{{ i + 1 }}. {{ name(players.find((p) => p.id === id)) }}
          <small class="handedness"
            >Bats: {{ players.find((p) => p.id === id)?.hit_side || "—" }} ·
            Throws:
            {{ players.find((p) => p.id === id)?.throw_side || "—" }}</small
          > </span
        ><select
          :disabled="disabled"
          :aria-label="
            'Fielding position for ' + name(players.find((p) => p.id === id))
          "
          :value="positions[id] || (label === 'Pitchers' ? 'P' : 'DH')"
          @change="
            emit('update:positions', {
              ...positions,
              [id]: $event.target.value,
            })
          "
        >
          <option
            v-for="position in [
              'P',
              'C',
              '1B',
              '2B',
              '3B',
              'SS',
              'LF',
              'CF',
              'RF',
              'DH',
            ]"
            :key="position"
          >
            {{ position }}
          </option></select
        ><span class="spacer"></span
        ><button
          :disabled="disabled || i === 0"
          aria-label="Move player up"
          @click="move(i, -1)"
        >
          ↑</button
        ><button
          :disabled="disabled || i === modelValue.length - 1"
          aria-label="Move player down"
          @click="move(i, 1)"
        >
          ↓</button
        ><button
          :disabled="disabled"
          aria-label="Remove player"
          @click="toggle(id)"
        >
          ×
        </button>
      </li>
    </ol>
  </section>
</template>
<style scoped>
.handedness {
  display: block;
  font-size: 11px;
  color: #b6c5dc;
}
.spacer {
  display: none;
}
li select {
  max-width: 65px;
  background: #172238;
  color: white;
  border: 1px solid #64738f;
  border-radius: 8px;
  padding: 8px;
}
.lineup {
  margin: 12px 0;
}
.opener {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
  width: 100%;
  padding: 20px;
  border-radius: 18px;
  background: #252939;
  color: white;
  text-transform: uppercase;
  text-align: left;
  font-size: 20px;
}
.opener span {
  font-size: 12px;
}
.picker {
  padding: 16px;
  background: #171d30;
  border-radius: 12px;
}
.picker > input {
  width: 100%;
  padding: 10px;
  background: #fff;
  color: #111;
  border-radius: 8px;
}
.picker label {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
}
.picker small {
  margin-left: auto;
}
li {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px;
}
li span {
  flex: 1;
}
li button {
  min-width: 38px;
  min-height: 38px;
  border-radius: 8px;
  background: #29334e;
}
button:disabled {
  opacity: 0.4;
}
button:focus-visible {
  outline: 3px solid #8de6ff;
}
</style>
