<script setup>
import { computed, ref, reactive } from "vue";
import ModalPlayer from "./dashboard/ModalPlayer.vue";
import { useAxiosAuth } from "@/composables/axios-auth.js";
import updatedLogo from "@/assets/img/login/assteslogin/updatedlogo.webp";

const { axiosGet } = useAxiosAuth();
const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
});

const resolvedPlayerId = computed(() =>
  props.item?.user_id || props.item?.user?.id || props.item?.player?.id || props.item?.id || null
)

const isOpenModal = ref(false);
const isLoading = reactive({ status: true });
const dataMetric = ref([]);
const dataScore = ref({});

const getFitnessPlayer = async () => {
  try {
    const pid = resolvedPlayerId.value;
    if (!pid) return;
    const { data } = await axiosGet(`player/fitness/${pid}`);
    const rows = data?.data;
    dataMetric.value = Array.isArray(rows) ? rows : (rows ? [rows] : []);
  } catch (error) {
    console.log(error);
  }
};

const getScorePlayer = async () => {
  try {
    const pid = resolvedPlayerId.value;
    if (!pid) return;
    const { data } = await axiosGet(`coach/statistics/${pid}`);
    dataScore.value = data.data;
  } catch (error) {
    console.log(error);
  }
};

const showModal = async () => {
  if (!resolvedPlayerId.value) return;
  isLoading.status = true;
  isOpenModal.value = true;
  try {
    await Promise.all([getScorePlayer(), getFitnessPlayer()]);
  } finally {
    isLoading.status = false;
  }
};

const close = () => {
  console.log(props.item.avatar);
  isOpenModal.value = false;
};
</script>
<template>
  <div
    class="card-player flex flex-col items-center cursor-pointer relative"
    @click="showModal()"
  >
    <!-- Player Name -->
    <div
      class="w-full text-center text-white font-fungo-800 text-[13px] py-1 px-2 truncate"
    >
      {{ item.name.full }}
    </div>

    <!-- Avatar with number badge -->
    <div class="relative mx-auto">
      <template v-if="item.avatar != null && item.avatar !== ''">
        <img
          :src="item.avatar"
          alt=""
          class="player-avatar object-cover"
          @error="(e) => { e.target.src = updatedLogo }"
        />
      </template>
      <img
        v-else
        :src="updatedLogo"
        alt=""
        class="player-avatar object-cover"
      />
      <!-- Number badge -->
      <div class="number-badge">#{{ item.shirt_number ?? "-" }}</div>
    </div>

    <!-- Stats row -->
    <div class="flex flex-row justify-around w-full mt-2 pb-2 px-2">
      <div class="text-center">
        <div
          class="text-[10px] text-gray-400 font-fungo-700 uppercase tracking-wide"
        >
          Velo
        </div>
        <div class="text-white font-fungo-800 text-[14px]">
          {{ dataScore?.velo ?? "-" }}
        </div>
      </div>
      <div class="border-l border-gray-600"></div>
      <div class="text-center">
        <div
          class="text-[10px] text-gray-400 font-fungo-700 uppercase tracking-wide"
        >
          EV
        </div>
        <div class="text-white font-fungo-800 text-[14px]">
          {{ dataScore?.ev ?? "-" }}
        </div>
      </div>
    </div>
  </div>
  <ModalPlayer
    @closeModal="close()"
    :isOpen="isOpenModal"
    :item="item"
    :response="dataMetric"
    :score="dataScore"
    v-if="isOpenModal"
  ></ModalPlayer>
</template>
<style scoped>
.card-player {
  background: linear-gradient(160deg, #002060 0%, #001030 100%);
  border-radius: 12px;
  width: 140px;
  min-height: 190px;
  margin: 6px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
}
.card-player:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
}
.player-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  border: 3px solid rgba(255, 255, 255, 0.15);
  display: block;
  margin: 0 auto;
}
.number-badge {
  position: absolute;
  bottom: 2px;
  right: 4px;
  background: #C00000;
  color: white;
  font-size: 11px;
  font-weight: 800;
  border-radius: 10px;
  padding: 1px 6px;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}
::-webkit-scrollbar-button {
  width: 0px;
  height: 0px;
}
::-webkit-scrollbar-thumb {
  background: #C00000;
  border: 0px none #ffffff;
  border-radius: 5px;
}
::-webkit-scrollbar-thumb:hover {
  background: #ffffff;
}
::-webkit-scrollbar-thumb:active {
  background: #000000;
}
::-webkit-scrollbar-track {
  background: #060b14;
  border: 22px solid #002060;
  border-radius: 4px;
}
::-webkit-scrollbar-track:hover {
  background: #C00000;
}
::-webkit-scrollbar-track:active {
  background: #333333;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
