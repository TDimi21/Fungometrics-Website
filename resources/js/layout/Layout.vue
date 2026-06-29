<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, computed } from "vue";
import { useUserStore } from "../store/user";
import { useTeamStore } from "../store/team";
import NavSidebar from "./NavigationSidebar.vue";
import { confirm } from "../utils/AlertPlugin";
import { toast } from "@/utils/AlertPlugin";
import {
  InputBase,
  BigButtonField,
  InutTel,
  LabelField,
} from "@/components/form";
import { SendMsgModal } from "@/components/shared";
import { ArrowLeftIcon, ArrowRightIcon } from "@/components/icons/";
import router from "../../router";
import { usePlayerStore } from "@/store/players.js";
import { useTrainingStore } from "@/store/training.js";
import { storeToRefs } from "pinia";
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  DialogDescription,
  TransitionRoot,
  TransitionChild,
} from "@headlessui/vue";
import { TableCancel } from "@/components/icons";
import Loader from "../components/Loader.vue";
import axios from "axios";
import { useAxiosAuth } from "@/composables/axios-auth.js";
import TickerBar from "./TickerBar.vue";
import PlayerTickerBar from "./PlayerTickerBar.vue";
import updatedLogo from "@/assets/img/login/assteslogin/updatedlogo.png";
import stadiumBackground from "@/assets/img/training/baseball field.jpeg";
import { getAuthToken } from "@/utils/authToken.js";

const { axiosGet } = useAxiosAuth();
const userStore = useUserStore();
const teamStore = useTeamStore();
const playerStore = usePlayerStore();
const trainingStore = useTrainingStore();
const { players, setPlayers } = storeToRefs(playerStore);
const { isShowMsgModal } = storeToRefs(trainingStore);
const { userData } = storeToRefs(userStore);
const { team, teams } = storeToRefs(teamStore);
const { setData } = userStore;
const { setTeam } = teamStore;
const resolveTeamId = (teamLike) => teamLike?.id_team ?? teamLike?.id ?? null;
const activeTeamId = computed(() => resolveTeamId(team.value));
const getTeamIdCandidates = (teamLike) => {
  const ids = [teamLike?.id_team, teamLike?.id]
    .filter(Boolean)
    .map((v) => String(v));
  return [...new Set(ids)];
};
const withTeamIdFallbackGet = async (buildPath, teamLike = team.value) => {
  const candidates = getTeamIdCandidates(teamLike);
  let lastError;
  for (const id of candidates) {
    try {
      return await axiosGet(buildPath(id));
    } catch (error) {
      lastError = error;
      const status = error?.response?.status;
      if (status !== 404 && status !== 403) throw error;
    }
  }
  throw lastError;
};

const isOpen = ref(false);
const isChange = ref(false);
const sessionCount = ref(0);
const teamJoinCode = ref('');
const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '';
const isLoading = reactive({ status: true });
const token = getAuthToken();
const temporalTeams = ref([]);
let playersOfTeam = ref([]);
function closeModal() {
  isOpen.value = false;
}
function openModal() {
  isOpen.value = true;
}
const userType = computed(() => userData.value?.type ?? null);
const dashboardHomeRoute = computed(() => userType.value === 'player' ? '/player-dashboard' : '/dashboard');
const coachDisplayName = computed(() => userData.value?.name?.full ?? "+ (503) 7851 - 7268");
let player = reactive({
  type: [],
  heightFt: 0,
  heightInch: 0,
  firstName: "",
  lastName: "",
  born: "",
  email: "",
  password: "",
  confirmPassword: "",
  mobileNumber: "",
});
import { getUiTheme, applyUiTheme } from "@/composables/useUiTheme";
let hasSidebar = reactive({ active: false });
const uiTheme = ref(getUiTheme());
const teamHeaderLogo = ref(updatedLogo);
const teamHeaderBackground = ref(stadiumBackground);
const socialLinks = {
  facebook: import.meta.env.VITE_SOCIAL_FACEBOOK_URL || import.meta.env.SOCIAL_FACEBOOK_URL || 'https://www.facebook.com/',
  x: import.meta.env.VITE_SOCIAL_X_URL || import.meta.env.SOCIAL_X_URL || 'https://x.com/',
  instagram: import.meta.env.VITE_SOCIAL_INSTAGRAM_URL || import.meta.env.SOCIAL_INSTAGRAM_URL || 'https://www.instagram.com/',
};
const sidebarTeamCardBackground = computed(() => team.value?.logo || '/app_logo.png');

const syncTopHeaderAssets = () => {
  const hasTeamLogo = Boolean(team.value?.logo);
  teamHeaderLogo.value = hasTeamLogo ? team.value.logo : updatedLogo;
  teamHeaderBackground.value = stadiumBackground;
};

const onTopHeaderLogoError = () => {
  teamHeaderLogo.value = updatedLogo;
};

const onTopHeaderBgError = () => {
  teamHeaderBackground.value = stadiumBackground;
};
const handleThemeChange = (event) => {
  const next = event?.detail?.theme;
  uiTheme.value = next === "light" ? "light" : "dark";
};
const toggleSidebar = () =>
  hasSidebar.active ? (hasSidebar.active = false) : (hasSidebar.active = true);
const logout = () => {
  confirm.fire().then((result) => {
    if (result.isConfirmed) {
      localStorage.clear();
      sessionStorage.clear();
      location.reload();
    }
  });
};

const getTeamsWithPalyers = async () => {
  if ((userData.value?.type ?? 'coach') == "coach") {
    try {
      isLoading.value = true;
      const { data } = await axiosGet("coach/teams");
      temporalTeams.value = data.data;
    } catch (error) {
      console.log(error);
    } finally {
      isLoading.value = false;
    }
  }
};

const submitAddPlayer = async () => {
  isLoading.status = !isLoading.status;
  if (
    player.mobileNumber == "" ||
    player.firstName == "" ||
    player.lastName == ""
  ) {
    toast.fire({
      icon: "warning",
      title: "Validation !!!",
      text: "You must complete all the fields",
    });
    isLoading.status = !isLoading.status;
  } else {
    let dataForm = new FormData();
    dataForm.append("phone", player.mobileNumber);
    dataForm.append("team", activeTeamId.value ?? '');
    dataForm.append("name[first]", player.firstName);
    dataForm.append("name[last]", player.lastName);
    const config = {
      headers: { Authorization: `Bearer ${token}` },
    };
    console.log("paso");
    await axios
      .post(api_url + "coach/add/players", dataForm, config)
      .then(async function (response) {
        let tempResponse = response.data.data;
        let playerToSetInStore = {
          id: tempResponse.id,
          name: {
            first: tempResponse.profile.first_name,
            last: tempResponse.profile.last_name,
            full:
              tempResponse.profile.first_name + tempResponse.profile.last_name,
          },
          avatar: "https://fungometrics.s3.amazonaws.com/updatedlogo.png",
          body: {
            ft: null,
            inch: null,
            weight: null,
            full_height: "'”",
            weight_data: " lb",
          },
          born: {
            date: null,
            age: 0,
          },
          number_in_shirt: null,
          throw_side: null,
          hit_side: null,
          positions: [],
        };

        players.value.push(playerToSetInStore);

        toast.fire({
          icon: "success",
          title: "Player Register",
          text: response.data.message,
        });
        isLoading.status = !isLoading.status;
        isOpen.value = false;
        router.go(router.currentRoute);
      })
      .catch(async function (error) {
        if (
          error.response.data.code === "001V" ||
          error.response.status === 422
        ) {
          const errorsObject = error.response.data.data.errors;
          let errorMessage = "";
          let isAllow = false;
          for (const [key, value] of Object.entries(errorsObject)) {
            if (!isAllow) {
              isAllow = true;
              errorMessage = value;
            }
          }
          await toast.fire({
            icon: "warning",
            title: "Team Warning !!!",
            text: errorMessage,
          });
        } else {
          await toast.fire({
            icon: "error",
            title: "Team Error !!!",
            text:
              "strike 3 is out, have a internal problem, " +
              error.response.data.message,
          });
        }
        console.log("paso x4");
        isLoading.status = !isLoading.status;
      });
  }
};
const changeTeam = async (info) => {
  isLoading.status = !isLoading.status;
  const selectedId = resolveTeamId(info);
  for (const item of temporalTeams.value) {
    if (String(resolveTeamId(item)) === String(selectedId)) {
      await setTeam(item);
      players.value = item.players;
      // await setPlayers(item.players);
      isChange.value = false;
      isLoading.status = !isLoading.status;
      router.go(router.currentRoute);
    }
  }
};

const openChangeTeamModal = () => {
  isChange.value = true;
};

const fetchSessionCount = async () => {
  if ((userData.value?.type ?? 'coach') !== 'coach' || !getTeamIdCandidates(team.value).length) {
    sessionCount.value = 0;
    return;
  }
  try {
    const { data } = await withTeamIdFallbackGet((id) => `coach/sessions/lasts/${id}`);
    const d = data?.data ?? {};
    sessionCount.value = [
      d.batting, d.bullpen, d.cage, d.live, d.weight_ball, d.long_toss, d.exit_velocity
    ].reduce((sum, arr) => sum + (Array.isArray(arr) ? arr.length : 0), 0);
  } catch {
    sessionCount.value = 0;
  }
};

const fetchTeamJoinCode = async () => {
  if ((userData.value?.type ?? 'coach') !== 'coach' || !getTeamIdCandidates(team.value).length) {
    teamJoinCode.value = '';
    return;
  }

  try {
    const response = await withTeamIdFallbackGet((id) => `coach/teams/${id}/code`);
    teamJoinCode.value = response?.data?.data?.join_code || response?.data?.join_code || '';
  } catch {
    teamJoinCode.value = '';
  }
};

onMounted(() => {
  // Recover persisted user payload when the store is empty after reload.
  if (!userData?.type) {
    try {
      const raw = sessionStorage.getItem('user');
      if (raw) {
        const parsed = JSON.parse(raw);
        const persisted = parsed?.userData ?? parsed?.state?.userData ?? null;
        if (persisted?.type) {
          setData(persisted);
        }
      }
    } catch (_) {
      // ignore malformed session data
    }
  }

  getTeamsWithPalyers();
  fetchSessionCount();
  fetchTeamJoinCode();
  syncTopHeaderAssets();
  applyUiTheme(uiTheme.value);

  window.addEventListener("ui-theme-changed", handleThemeChange);
  window.addEventListener("open-add-player-modal", openModal);
  window.addEventListener("open-change-team-modal", openChangeTeamModal);
});

onUnmounted(() => {
  window.removeEventListener("ui-theme-changed", handleThemeChange);
  window.removeEventListener("open-add-player-modal", openModal);
  window.removeEventListener("open-change-team-modal", openChangeTeamModal);
});

watch(
  () => activeTeamId.value,
  () => {
    fetchSessionCount();
    fetchTeamJoinCode();
    syncTopHeaderAssets();
  }
);
</script>

<template>
  <Loader v-show="!isLoading.status" />
  <div class="layout-shell flex overflow-hidden bg-[#060b14] min-h-screen" :class="uiTheme === 'light' ? 'theme-light' : 'theme-dark'">

    <!-- Left Sidebar -->
    <aside
      class="fixed z-30 h-full top-0 left-0 flex flex-shrink-0 flex-col transition-[width,transform] duration-500 bg-transparent backdrop-blur-md border-r border-white/10 overflow-hidden"
      :class="hasSidebar.active ? 'w-72 translate-x-0' : 'w-0 -translate-x-full'"
    >
      <div class="relative flex-1 flex flex-col min-h-0 w-72">
        <div class="flex-1 flex flex-col overflow-y-auto">
          <!-- Logo + collapse button -->
          <div class="mt-2 pb-3 relative">
            <button
              @click="toggleSidebar"
              class="absolute right-3 top-0 text-white/40 hover:text-white transition-colors"
              title="Collapse sidebar"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7M18 19l-7-7 7-7"/></svg>
            </button>
            <RouterLink
              :to="userType == 'player' ? '/player-dashboard' : '/dashboard'"
              class="grid items-center cursor-pointer"
            >
              <img
                src="../assets/img/login/assteslogin/updatedlogo.png"
                alt="Main fungo logo"
                width="170"
                height="166"
                class="mx-auto -mt-1"
              />
            </RouterLink>
            <div class="absolute w-full h-[3px] bg-gradient-to-r from-[#002060] to-[#C00000] bottom-0"></div>
          </div>

          <!-- Team code (coach only) -->
          <div
            v-if="userType === 'coach'"
            class="mx-3 mt-2 mb-2 flex justify-center"
          >
            <div class="inline-flex flex-col overflow-hidden rounded-lg border border-red-400/60 shadow-[0_6px_18px_rgba(192,0,0,0.35)]">
              <div class="bg-[#ff3b57] px-4 py-1 text-[9px] leading-none font-black tracking-[0.22em] text-white text-center">CODE</div>
              <div class="bg-[#b10024] px-4 py-1.5 text-center">
                <span class="text-[16px] leading-none font-black tracking-[0.14em] text-white">{{ (teamJoinCode || '—').toUpperCase() }}</span>
              </div>
            </div>
          </div>

          <!-- Team card (coach only) -->
          <div
            v-if="userType === 'coach'"
            class="mx-3 mb-2 mt-0 relative overflow-hidden rounded-xl border border-white/20 shadow-lg"
          >
            <div
              class="absolute inset-0 bg-cover bg-center opacity-20"
              :style="{ backgroundImage: `url(${sidebarTeamCardBackground})` }"
            ></div>
            <div class="absolute inset-0 bg-[#001030]/80"></div>

            <div class="relative z-10 p-3 flex flex-col gap-3 cursor-pointer hover:bg-white/5 transition" @click="openChangeTeamModal" title="Click to switch team">
              <!-- Logo + name -->
              <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-lg overflow-hidden border border-white/20 bg-slate-900 shrink-0">
                  <img v-if="team?.logo" :src="team.logo" alt="Team" class="h-full w-full object-cover" />
                  <div v-else class="h-full w-full flex items-center justify-center text-xs font-black text-white/30">FM</div>
                </div>
                <div class="min-w-0">
                  <p class="text-white font-black text-sm truncate leading-tight">{{ team?.name ?? 'My Team' }}</p>
                  <p class="text-white/50 text-[10px] leading-tight mt-0.5">Player development tracking · Sessions · Stats</p>
                </div>
              </div>
            </div>

            <!-- Add Players button -->
            <div class="relative z-10 px-3 pb-1">
              <button
                type="button"
                class="w-full rounded-lg border border-red-400/60 bg-red-500/20 py-1.5 text-center text-[11px] font-black tracking-wider text-red-200 hover:bg-red-500/30 transition"
                @click.stop="openModal"
              >
                + ADD PLAYERS
              </button>
            </div>

            <!-- Stats row -->
            <div class="relative z-10 grid grid-cols-2 gap-1.5 px-3 pb-3 pt-2">
              <div class="rounded-lg border border-white/10 bg-white/10 p-2 text-center">
                <div class="text-[9px] font-black uppercase tracking-widest text-white/45">Players</div>
                <div class="mt-0.5 text-lg font-black text-white">{{ players?.length ?? 0 }}</div>
              </div>
              <div class="rounded-lg border border-white/10 bg-white/10 p-2 text-center">
                <div class="text-[9px] font-black uppercase tracking-widest text-white/45">Sessions</div>
                <div class="mt-0.5 text-lg font-black text-white">{{ sessionCount }}</div>
              </div>
            </div>
          </div>

          <!-- Nav links -->
          <NavSidebar :collapse="hasSidebar.active" />

          <!-- Bottom actions -->
          <div class="px-4 pb-4 space-y-2 border-t border-white/10 pt-3 mt-2">
            <div v-if="userType === 'player'">
              <RouterLink to="/change-password" class="sidebar-action mb-2 block">CHANGE PASSWORD</RouterLink>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Floating re-open button when sidebar is collapsed -->
    <button
      v-if="!hasSidebar.active"
      @click="toggleSidebar"
      class="fixed left-0 top-1/2 -translate-y-1/2 z-40 bg-[#C00000] hover:bg-[#A00000] transition-colors rounded-r-xl px-1 py-2 shadow-lg"
      title="Open sidebar"
    >
      <img src="../assets/img/login/assteslogin/updatedlogo.png" alt="Open sidebar" class="w-10 h-10 object-contain" />
    </button>

    <!-- Main content -->
    <div
      class="h-full w-full relative overflow-y-auto transition-[margin] duration-500"
      :class="hasSidebar.active ? 'ml-0 lg:ml-72' : 'ml-0'"
    >
      <header class="top-brand-nav w-full">
        <div class="top-brand-content">
          <div class="top-brand-left">
            <RouterLink :to="dashboardHomeRoute" class="top-brand-logo-wrap" title="Go to dashboard">
              <img
                :src="teamHeaderLogo"
                alt="Team logo"
                class="top-brand-logo"
                @error="onTopHeaderLogoError"
              />
            </RouterLink>
          </div>

          <div class="top-brand-ticker">
            <PlayerTickerBar v-if="userType === 'player'" />
            <TickerBar v-else />
          </div>

          <div class="top-brand-social">
            <a :href="socialLinks.facebook" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="Facebook" title="Facebook">
              <svg viewBox="0 0 24 24" class="social-icon" fill="currentColor"><path d="M13.5 21v-8h2.7l.4-3h-3.1V8.2c0-.9.3-1.5 1.6-1.5h1.7V4c-.3 0-1.4-.1-2.6-.1-2.6 0-4.3 1.5-4.3 4.3V10H7v3h2.9v8h3.6z"/></svg>
            </a>
            <a :href="socialLinks.x" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="X" title="X">
              <svg viewBox="0 0 24 24" class="social-icon" fill="currentColor"><path d="M18.2 3h2.9l-6.4 7.3L22.2 21h-5.9l-4.6-6-5.2 6H3.6l6.9-7.8L1.8 3h6l4.1 5.4L18.2 3zm-1 16h1.6L7 4.9H5.3L17.2 19z"/></svg>
            </a>
            <a :href="socialLinks.instagram" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="Instagram" title="Instagram">
              <svg viewBox="0 0 24 24" class="social-icon" fill="currentColor"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm8.2 1.8H8A4.2 4.2 0 0 0 3.8 8v8a4.2 4.2 0 0 0 4.2 4.2h8a4.2 4.2 0 0 0 4.2-4.2V8A4.2 4.2 0 0 0 16 3.8zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.8a3.2 3.2 0 1 0 0 6.4 3.2 3.2 0 0 0 0-6.4zm5.4-2a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4z"/></svg>
            </a>
            <div class="flex items-center gap-1 ml-3 pl-3 border-l border-white/20">
              <RouterLink to="/settings" class="flex items-center gap-1 text-white/70 hover:text-white text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/15 border border-white/10 transition" title="Settings">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                Settings
              </RouterLink>
              <button @click="logout" type="button" class="flex items-center gap-1 text-white/70 hover:text-white text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-lg bg-white/5 hover:bg-red-500/20 border border-white/10 hover:border-red-500/40 transition" title="Log Out">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Log Out
              </button>
            </div>
          </div>
        </div>
      </header>

      <main
        class="app-main-shell min-h-screen pt-6 pb-24 px-0 overflow-hidden bg-[#060b14]"
        v-if="userType !== 'player'"
      >
        <div class="screen-stage">
          <slot />
        </div>
      </main>
      <main
        class="app-main-shell min-h-screen px-0 overflow-hidden bg-fungo-gray2"
        v-if="userType === 'player'"
      >
        <div class="screen-stage">
          <slot />
        </div>
      </main>
    </div>
  </div>
  <div v-if="isOpen">
    <div class="fixed inset-0 z-50 flex justify-center items-center">
      <div
        class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[50%] max-h-[50%] lg:min-h-[40%] lg:max-h-[40%] w-[85%] md:w-[100%] ml-3 lg:ml-0"
      >
        <div>
          <div class="flex flex-row w-[100%] items-center mb-3 px-4">
            <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">
              Add player
            </h1>
            <div
              class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]"
              @click="isOpen = false"
            >
              <img
                alt="Icon close view"
                src="../assets/img/register/cancel.svg"
              />
            </div>
          </div>
          <div
            class="bg-fungo-gray2 flex flex-row w-[100%] items-center mb-5 py-10 px-[3%]"
          >
            <form
              action=""
              name="add-player"
              class="flex flex-col lg:flex-row flex-wrap items-center space-x-0 lg:space-x-3 w-[95%] lg:w-[100%]"
            >
              <div class="mb-2">
                <div>
                  <LabelField text="First name" :required="true" />
                  <InputBase v-model="player.firstName" />
                </div>
              </div>
              <div class="mb-2">
                <div>
                  <LabelField text="Last name" :required="true" />
                  <InputBase v-model="player.lastName" />
                </div>
              </div>
              <div class="mb-2">
                <div>
                  <LabelField text="Mobile number" :required="true" />
                  <InutTel v-model="player.mobileNumber" inputType="tel" />
                </div>
              </div>
            </form>
          </div>
          <div class="flex flex-row justify-center">
            <div class="justify-center">
              <button
                class="grid place-items-center grid-flow-col flex-row rounded-button-right w-[200px] lg:w-[250px] px-2 py-1 text-xl md:text-[12px] lg:text-[16px] bg-fungo-red text-white hover:bg-fungo-red-hover"
                type="submit"
                @click="submitAddPlayer"
              >
                <img
                  alt="button register coach"
                  class="w-4 h-4 md:w-6 md:h-6 mx-2 md:mx-0"
                  src="../assets/img/login/assteslogin/ballbutton.svg"
                />
                <span class="mx-2">Add</span>
                <div class="text-white mx-2 animate-bounce-r">
                  <ArrowRightIcon color="ffffff" w="50" h="50" />
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
  </div>
  <div v-if="isChange">
    <div class="fixed inset-0 z-50 flex justify-center items-center">
      <div
        class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[50%] max-h-[50%] lg:min-h-[40%] lg:max-h-[40%] w-[85%] md:w-[100%] ml-3 lg:ml-0"
      >
        <div>
          <div class="flex flex-row w-[100%] items-center mb-1 px-4">
            <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">
              Change team
            </h1>
            <div
              class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]"
              @click="isChange = false"
            >
              <img
                alt="Icon close view"
                src="../assets/img/register/cancel.svg"
              />
            </div>
          </div>
          <div class="flex flex-row w-[100%] items-center mb-2 py-4 px-[2%]">
            <div
              class="grid grid-rows-2 grid-cols-1 w-[100%] items-center gap-4 overflow-y-auto min-h-[50%] max-h-[50%]"
            >
              <div
                class="bg-fungo-gray4 border-2 border-fungo-gray3 flex flex-row justify-between w-[100%] md:w-[100%] md:max-w-[100%] py-5 pl-1 lg:pl-4 rounded-xl"
              >
                <div class="w-[100px] max-w-[100px]">
                  <img
                    alt="Team logo"
                    class="h-full object-center object-cover mx-auto"
                    :src="team?.logo"
                  />
                </div>
                <div class="w-[400px] max-w-[400px] pl-2 lg:pl-5">
                  <div
                    class="flex flex-col text-[12px] lg:text-[14px] items-start"
                  >
                    <text class="font-fungo-700 text-fungo-blue2 pb-2"
                      >Team</text
                    >
                    <text
                      class="text-fungo-darkblue font-fungo-800 text-[14px] lg:text-[16px]"
                      >{{ team?.name ?? "item.number_in_shirt" }}</text
                    >
                    <text
                      class="font-fungo-400 text-fungo-darkblue text-[14px] lg:text-[16px] pt-2"
                    >
                      <span
                        class="font-fungo-700 text-fungo-darkblue text-[14px] lg:text-[16px]"
                        >Coach:</span
                      >
                      {{ coachDisplayName }}
                    </text>
                  </div>
                </div>
                <div
                  class="w-[100px] max-w-[100px] flex justify-center items-center"
                >
                  <input
                    type="radio"
                    name="choose_team"
                    :id="`choose_${team?.id}`"
                    checked
                    class="appearance-none checked:bg-green-500 autofill:bg-green-500 text-green-500 indeterminate:bg-fungo-gray6 default:ring-2 valid:border-fungo-darkblue h-8 w-8"
                  />
                </div>
              </div>
              <template v-for="(item, index) in teams" :key="item.id">
                <div
                  v-if="item.id != team?.id"
                  class="bg-fungo-gray4 border-2 border-fungo-gray3 flex flex-row justify-between w-[100%] md:w-[100%] md:max-w-[100%] py-5 pl-1 lg:pl-4 rounded-xl"
                >
                  <div class="w-[100px] max-w-[100px]">
                    <img
                      alt="Team logo"
                      class="h-full object-center object-cover mx-auto"
                      :src="item.logo"
                    />
                  </div>
                  <div class="w-[400px] max-w-[400px] pl-2 lg:pl-5">
                    <div
                      class="flex flex-col text-[12px] lg:text-[14px] items-start"
                    >
                      <text class="font-fungo-700 text-fungo-blue2 pb-2"
                        >Team</text
                      >
                      <text
                        class="text-fungo-darkblue font-fungo-800 text-[14px] lg:text-[16px]"
                        >{{ item.name ?? "item.number_in_shirt" }}</text
                      >
                      <text
                        class="font-fungo-400 text-fungo-darkblue text-[14px] lg:text-[16px] pt-2"
                      >
                        <span
                          class="font-fungo-700 text-fungo-darkblue text-[14px] lg:text-[16px]"
                          >Coach:</span
                        >
                          {{ coachDisplayName }}
                      </text>
                    </div>
                  </div>
                  <div
                    class="w-[100px] max-w-[100px] flex justify-center items-center"
                  >
                    <input
                      type="radio"
                      name="choose_team"
                      :id="`choose_${item.id}`"
                      class="appearance-none checked:bg-green-500 autofill:bg-green-500 text-green-500 indeterminate:bg-fungo-gray6 default:ring-2 valid:border-fungo-darkblue h-8 w-8"
                      @click="changeTeam(item)"
                    />
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
  </div>

  <SendMsgModal v-if="isShowMsgModal" />
</template>
<style lang="css" scoped>
@keyframes bounce {
  0%,
  100% {
    transform: translateX(-25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-r {
  animation: bounce 1s infinite;
}

@keyframes bouncel {
  0%,
  100% {
    transform: translateX(25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-l {
  animation: bouncel 1s infinite;
}

.rounded-button-right {
  border-radius: 30px 10px 10px 30px;
}

.rounded-button-left {
  border-radius: 10px 30px 30px 10px;
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
  background: #e41111;
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
  background: #666666;
  border: 22px solid #918383;
  border-radius: 4px;
}
::-webkit-scrollbar-track:hover {
  background: #e41111;
}
::-webkit-scrollbar-track:active {
  background: #333333;
}
::-webkit-scrollbar-corner {
  background: transparent;
}

.size-icon {
  @apply w-2 h-2;
}

.top-action-btn {
  display: inline-flex;
  align-items: center;
  border-radius: 0.75rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.08);
  padding: 0.5rem 1rem;
  font-size: 0.75rem;
  color: #fff;
  transition: 0.2s ease;
}

.team-switch-card {
  border-radius: 0.75rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.08);
  padding: 0.5rem 1rem;
  transition: 0.2s ease;
}

.sidebar-action {
  display: block;
  width: 100%;
  text-align: center;
  border-radius: 0.75rem;
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: rgba(255, 255, 255, 0.08);
  padding: 0.5rem;
  color: #fff;
  font-size: 0.875rem;
  transition: 0.2s ease;
}

.sidebar-action-danger {
  border-color: rgba(239, 68, 68, 0.6);
  color: #fca5a5;
}

.top-action-btn:hover,
.team-switch-card:hover,
.sidebar-action:hover {
  background: rgba(255, 255, 255, 0.18);
}

.sidebar-action-danger:hover {
  background: rgba(239, 68, 68, 0.2);
}

.layout-shell.theme-light {
  background: #eef2f7 !important;
}

.layout-shell.theme-light aside {
  background: #f8fafc !important;
  border-right: 1px solid rgba(15, 23, 42, 0.08);
}

.layout-shell.theme-light main {
  background: #eef2f7 !important;
}

.layout-shell.theme-light .sidebar-action {
  color: #0f172a;
  border-color: rgba(15, 23, 42, 0.18);
  background: rgba(15, 23, 42, 0.06);
}

.layout-shell.theme-light .sidebar-action:hover {
  background: rgba(15, 23, 42, 0.12);
}

.layout-shell.theme-dark .app-main-shell {
  background-color: rgba(0, 32, 96, 0.6) !important;
}

.top-brand-nav {
  --top-brand-height: 92px;
  position: sticky;
  top: 0;
  z-index: 35;
  min-height: var(--top-brand-height);
  background: rgba(0, 31, 77, 0.92);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.16);
}

.top-brand-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.top-brand-overlay {
  position: absolute;
  inset: 0;
  background: transparent;
}

.top-brand-content {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  min-height: var(--top-brand-height);
  padding: 0.5rem 1rem;
}

.top-brand-left {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  min-width: 0;
}

.top-brand-ticker {
  flex: 1;
  min-width: 0;
}

.top-brand-ticker :deep(.ticker-bar) {
  height: 38px;
  border-top: 1px solid rgba(255, 255, 255, 0.14);
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 9999px;
}

.top-brand-logo-wrap {
  height: 58px;
  width: auto;
  aspect-ratio: 1 / 1;
  border-radius: 0.7rem;
  border: 2px solid rgba(255, 255, 255, 0.25);
  background: rgba(2, 6, 23, 0.7);
  overflow: hidden;
  flex-shrink: 0;
}

.top-brand-logo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.top-brand-subtitle {
  margin: 0;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-weight: 800;
  color: rgba(134, 239, 172, 0.95);
}

.top-brand-title {
  margin: 0.15rem 0 0;
  font-size: 1.4rem;
  line-height: 1.1;
  font-weight: 900;
  color: #fff;
}

.top-brand-social {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.social-link {
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  background: rgba(0, 0, 0, 0.22);
  color: #f8fafc;
  transition: 0.2s ease;
}

.social-link:hover {
  background: rgba(255, 255, 255, 0.18);
  border-color: rgba(125, 211, 252, 0.9);
  color: #7dd3fc;
}

.social-icon {
  width: 14px;
  height: 14px;
}

@media (max-width: 768px) {
  .top-brand-nav {
    --top-brand-height: 78px;
    min-height: var(--top-brand-height);
  }

  .top-brand-ticker :deep(.ticker-label) {
    display: none;
  }

  .top-brand-logo-wrap {
    height: 46px;
  }

  .top-brand-ticker :deep(.ticker-item) {
    padding: 0 7px;
    font-size: 12px;
  }
}

.btn-logout {
  @apply text-fungo-darkblue hover:text-fungo-darkblue-hover grid grid-cols-1 items-center content-center
  justify-items-center h-full px-2 lg:px-5 cursor-pointer text-xs lg:text-lg;
}
</style>
