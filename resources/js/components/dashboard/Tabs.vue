<script setup>
  import { ref } from 'vue'
  const props = defineProps({
    tabs: {
      type: Array,
      required: true,
      default: []
    },
    active: {
      type: String,
      required: false,
      default: ""
    },
  })

  const activeTab = ref(props.tabs[0].value)
</script>

<template>
  <div class="tabs">
    <div class="tabs-header">
      <div class="encabezado">
        <div v-for="(item, index) in tabs" :key="index" class="tab mx-0 sm:mx-2 md:mx-2 lg:mx-4 my-3"
        :class="{active: item.value == activeTab}" @click="activeTab = item.value">
          <span>{{ item.title }}</span>
        </div>
      </div>
    </div>

    <div class="tabs-content">
      <slot name="content" :active="activeTab"/>
    </div>
  </div>
</template>

<style scoped>

.tabs-header{
  background-color: white;
  border-bottom: 1px solid #E7EAEE;
}

.encabezado{
  @apply flex text-center max-w-[90%];
  /* text-align: center;
  max-width: 80%; */
}

.tabs-content{
  margin-bottom: 3%;
}

.tab{
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 50px;
  row-gap: 0.2rem;
  align-items: center;
  justify-content: center;
  background-color: white;
  color: #082247;
  font-weight: 800;
  cursor: pointer;
  transition: 0.25s all ease;
}

.active{
  color: #E10600;
}
</style>
