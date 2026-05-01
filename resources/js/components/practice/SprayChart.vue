<script setup>
import { PrintFieldData } from '@/components/shared'
import {
  defineProps, ref, onUpdated, onMounted, watch
} from 'vue'
import { dataCoordinates } from '../../utils/dataCoordinatesField';

const seletedCage = ref("T")
let coordinates = ref([])

const props = defineProps({
  coordinates: {
    Type: Array
  },
  filterPosition: {
    Type: Boolean,
    default: false
  },
  position: {
    Type: String,
  }
})

onMounted(() => {
  coordinates.value = []
  findlocation()
})

const groundBall = ref(0);

const ballLocationFieldSpray = (sprayAngle, distance, type) => {
  if (distance > 0) {
    let newSpray = ((90 - sprayAngle) * Math.PI) / 180
    let horizontalPositionInFt = distance * Math.cos(newSpray)
    let verticalPositionInFt = distance * Math.sin(newSpray)

    let horizontalBlocks = Math.round(horizontalPositionInFt / 5)
    let verticalBlocks = Math.round(verticalPositionInFt / 4.5)

    if (horizontalBlocks < -40) {
      horizontalBlocks = -40
    } else if (horizontalBlocks > 39) {
      horizontalBlocks = 39
    }

    if (verticalBlocks > 66) {
      verticalBlocks = 66
    }

    horizontalBlocks = horizontalBlocks > 80 ? 80 : horizontalBlocks
    verticalBlocks = verticalBlocks > 80 ? 80 : verticalBlocks

    let ballLocation = (3267 + horizontalBlocks * 80) - verticalBlocks

    ballLocation = ballLocation > 6400 ? 6400 : ballLocation
    ballLocation = ballLocation < 1 ? 1 : ballLocation

    coordinates.value.push({ point: Math.floor(ballLocation), feature: type.toUpperCase() })
  } else {
    coordinates.value.push({ point: 3267, feature: type.toUpperCase() })
  }
}

const findlocation = () => {
  let val = [];

  if (props.position != 'a') {
    if (props.filterPosition) {
      for (const iterator in props.coordinates) {
        ballLocationFieldSprayByLocation(props.coordinates[iterator].spray_angle, props.coordinates[iterator].distance_travel, props.coordinates[iterator].type)
      }
    } else {
      val = props.coordinates.filter((value) => value.type == props.position)
      for (const iterator of val) {
        ballLocationFieldSpray(iterator.spray_angle, iterator.distance_travel, iterator.type)
      }
    }
  } else {
    for (const iterator in props.coordinates) {
      ballLocationFieldSpray(props.coordinates[iterator].spray_angle, props.coordinates[iterator].distance_travel, props.coordinates[iterator].type)
    }
  }
}

const ballLocationFieldSprayByLocation = (sprayAngle, distance, type) => {
  let newSpray = ((90 - sprayAngle) * Math.PI) / 180
  let horizontalPositionInFt = distance * Math.cos(newSpray)
  let verticalPositionInFt = distance * Math.sin(newSpray)

  let horizontalBlocks = Math.round(horizontalPositionInFt / 5)
  let verticalBlocks = Math.round(verticalPositionInFt / 4.5)

  if (horizontalBlocks < -40) {
    horizontalBlocks = -40
  } else if (horizontalBlocks > 39) {
    horizontalBlocks = 39
  }

  if (verticalBlocks > 66) {
    verticalBlocks = 66
  }

  horizontalBlocks = horizontalBlocks > 80 ? 80 : horizontalBlocks
  verticalBlocks = verticalBlocks > 80 ? 80 : verticalBlocks

  let ballLocation = (3267 + horizontalBlocks * 80) - verticalBlocks

  ballLocation = ballLocation > 6400 ? 6400 : ballLocation
  ballLocation = ballLocation < 1 ? 1 : ballLocation

  let ball = dataCoordinates.find((value) => value.zone.charAt(0) == props.position && ballLocation == value.point)
  if (ball) {
    coordinates.value.push({ point: Math.floor(ballLocation), feature: type.toUpperCase() })
  }

}
</script>
<template>
  <div class="flex flex-col col-span-12 px-2 md:col-span-9 xl:col-span-6">
    <div v-if="seletedCage != 'B'" class="mx-auto mt-12">
      <PrintFieldData :key="seletedCage" :fieldCoordinates="coordinates" :typeOfCondition="'trajectory'" />
    </div>
  </div>
</template>
<style scoped>
.cell {
  @apply h-[1em] border border-black;
  cursor: pointer;
}


.top-panel {
  @apply bg-white;
  transform: perspective(400px) rotatex(-30deg);
}

.left-panel {
  @apply bg-white;
  transform: perspective(400px) rotatey(30deg);
}

.right-panel {
  @apply bg-white;
  transform: perspective(400px) rotatey(-30deg);
}

.bottom-panel {
  background-image: url('../../assets/img/training/rombos.png');
  background-position-y: center;
  margin-top: -100px;
  transform: perspective(400px) rotatex(80deg)
}

.front-panel {
  @apply bg-white;
  transform: scale(0.8)
}

.ball {
  border: 3px solid red;
}

.ground {
  transform: rotate3d(1, 1, 1, 45deg);
}
</style>
