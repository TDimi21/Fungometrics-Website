

<template>
  <div>
    <div class="container-loader">
      <div class="loader"><img alt="" src="../assets/img/loader.gif"></div>
    </div>
  </div>
</template>
<script>
export default {
  name: 'Loader'
}
</script>
<style scoped>

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}


</style>
