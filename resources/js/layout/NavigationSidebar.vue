<script setup>
import {reactive, ref, onMounted} from 'vue'
import { TransitionRoot } from '@headlessui/vue'
import ArrowDownIcon from "../components/icons/ArrowDownIcon.vue";
import iconDashboard from "../assets/img/icons/i-dashboard.svg";
import iconStartPractice from "../assets/img/icons/i-start-practice.svg";
import iconSessionPractice from "../assets/img/icons/i-practice-session.svg";
import iconRoster from "../assets/img/icons/i-roster.svg";
import iconManageTeam from "../assets/img/icons/i-manage-team.svg";
import { useUserStore } from "@/store/user";

let isActiveDropdonw = reactive({isVisible: false, key: []})

const { userData } = useUserStore();

const sidebarItems = ref([
  {
    title: 'Dashboard',
    iconPath: iconDashboard,
    url: '/dashboard'
  },
  {
    title: 'Roster',
    iconPath: iconRoster,
    url: '/roster'
  },
  {
    title: 'Start practice',
    iconPath: iconStartPractice,
    startWith: '/create',
    child: [
      {
        title: 'Batting practice',
        url: '/create/batting'
      },
      {
        title: 'Bullpen practice',
        url: '/create/bullpen'
      },
      {
        title: 'Cage practice',
        url: '/create/cage'
      },
      {
        title: 'Training mode',
        url: '/create/mode'
      },
      {
        title: 'LiveAB mode',
        url: '/create/live'
      }
    ]
  },
  {
    title: 'Practice Sessions',
    iconPath: iconSessionPractice,
    startWith: '/training',
    child: [
      {
        title: 'Batting practice',
        url: '/training/batting'
      },
      {
        title: 'Bullpen practice',
        url: '/training/bullpen'
      },
      {
        title: 'Cage practice',
        url: '/training/cage'
      },
      {
        title: 'Training mode',
        url: '/training/training-mode'
      },
      {
        title: 'LiveAB mode',
        url: '/trainingb/live'
      }
    ]
  },
  {
    title: 'Statistics',
    iconPath: iconStartPractice,

    url: '/statistic',
  },
  {
    title: 'Manage Team',
    iconPath: iconManageTeam,
    url: '/manage'
  },
])

const showDropdown = (index) => {
  if (isActiveDropdonw.key.includes(index)) {
    isActiveDropdonw.key = isActiveDropdonw.key.filter(i => i != index)
  } else {

    isActiveDropdonw.key.push(index)
  }
}

const props = defineProps({
  collapse: {
    type: Boolean,
    required: true,
  }
})

onMounted(() => {
  if (userData.type == "player") {
    sidebarItems.value = [
      {
        title: 'Dashboard',
        iconPath: iconDashboard,
        url: '/player-dashboard'
      },
      {
        title: 'Batting sessions',
        iconPath: iconSessionPractice,
        url: '/training-player/batting'
      },
      {
        title: 'Bullpen sessions',
        iconPath: iconSessionPractice,
        url: '/training-player/bullpen'
      },
      {
        title: 'Cage sessions',
        iconPath: iconSessionPractice,
        url: '/training-player/cage'
      },
      {
        title: 'Training sessions',
        iconPath: iconSessionPractice,
        url: '/training-player/training-mode'
      },
      {
        title: 'Practice Sessions',
        iconPath: iconStartPractice,
        startWith: '/sesion',
        child: [
          {
            title: 'Batting practice',
            url: '/training/batting'
          },
          {
            title: 'Bullpen practice',
            url: '/training/bullpen'
          },
          {
            title: 'Cage practice',
            url: '/training/cage'
          },
          {
            title: 'Training mode',
            url: '/training/training-mode'
          },
        ]
      }
    ]
  }
})
</script>

<template>
  <nav class="flex-1 overflow-x-hidden overflow-y-auto text-white">
    <ul class="pb-2 divide-y-2 divide-fungo-dark-gray">
      <li v-for="(item, index) in sidebarItems">
        <button
          v-if="item.child"
          class="flex flex-row flex-nowrap items-center py-5 relative w-full"
          :class="{'router-link-active router-link-exact-active' : $route.path.includes(item.startWith) }"
          @click="showDropdown(index)"
        >
          <img :alt="item.title" :src="item.iconPath" class="pl-4 pr-2.5">
          <span :class="{'hidden' : !props.collapse }">{{ item.title }} {{ item.child.url }}</span>
          <ArrowDownIcon />
        </button>

        <RouterLink
          v-else="!item.child"
          :to="{ path: item.url }"
                    class="flex flex-row flex-nowrap items-center py-5 relative">
          <img :alt="item.title" :src="item.iconPath" class="pl-4 pr-2.5">
          <span :class="{'hidden' : !props.collapse }">{{ item.title }}</span>
        </RouterLink>

        <!-- subitems -->
        <TransitionRoot
          :show="isActiveDropdonw.key.includes(index)"
          enter="transition-opacity duration-75"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="transition-opacity duration-150"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <ul
            v-if="isActiveDropdonw.key.includes(index)"
            class="bg-fungo-dark-gray pl-10 py-5 list-disc marker:text-fungo-lightblue block"
          >

            <li v-for="child in item.child" class="leading-8">
              <RouterLink
                :to="child.url"
                class="flex flex-row flex-nowrap items-center"
              >
                {{ child.title }}
              </RouterLink>
            </li>

          </ul>
        </TransitionRoot>

      </li>
    </ul>
  </nav>
</template>

<style scoped>
nav > ul > li > .router-link-active.router-link-exact-active {
  font-weight: 700;
  font-size: 16px;
  line-height: 24px;
  color: #C00000;
  border-left: 3px solid;
  border-color: #C00000;
}

nav > ul > li > .router-link-active.router-link-exact-active::before {
  top: calc(50% - 7px);
  content: "";
  position: absolute;
  left: 0;
  width: 0;
  height: 0;
  border: 8px solid transparent;
  border-top-color: transparent;
  border-bottom-color: transparent;
  border-right-color: transparent;
  border-left-color: #C00000;
}

nav > ul ul > li > .router-link-active.router-link-exact-active{
  color: #ADE8F4;
  font-weight: 600;
}
</style>
