<script setup xmlns="http://www.w3.org/1999/html">
import Layout from "../../layout/Layout.vue"
import { useTeamStore } from "../../store/team";
import { useTrainingStore } from "../../store/training";
import { reactive, ref, defineProps, onMounted, watch } from "vue";
import CageIcon from "../../components/icons/CageIcon.vue";
import { toast } from "@/utils/AlertPlugin"
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import VelocityInput from "../../components/VelocityInput.vue";
import Loader from "../../components/Loader.vue";
import { useRouter } from 'vue-router'
import { useUserStore } from "@/store/user";
import { usePlayerStore } from '@/store/players.js'
import DefaultImg from '@/assets/img/noavatar.png'

const router = useRouter()
const { team, teams } = useTeamStore();
const { axiosPost, axiosPut } = useAxiosAuth()
const { players } = usePlayerStore()
const { userData } = useUserStore();
const isLoading = reactive({ status: true })
const training = useTrainingStore();
const playerCard = ref(training.trainingActive.players[0]);
const playerList = [...training.trainingActive.players];
const playerToAddList = ref([]);
const balls = ref(0);
const sort = ref(training.trainingActive.sort ?? 0);
const change = ref(0);
const endNote = ref('');
const picked = ref('progress');
const dataPlayer = ref('');
const dataProcess = ref({
  player: '',
  team: team.id,
  velocity: 0,
  practice: training.trainingActive.id,
  cage_mark: 0,
  cage_position: '',
  data_angel: 0,
  data_spray: 0
});

const velocity = ref(0)

const cageStadistics = ref({
  distanceRaveled: 0,
  launchAngle: 0,
  sprayAngle: 0,
  groundBall: false
})

const props = defineProps({
  cageHeight: {
    Type: Number
  },
  lengthCage: {
    Type: Number
  },
  widthCage: {
    Type: Number
  },
})

const lengthOfCage = ref([])
const heightOfCage = ref([])
const cellsRigth = ref([]);
const cellsLeft = ref([])
const cellsTop = ref([])
const cellsBack = ref([])
const isOpen = ref(false)
const isOpenAdd = ref(false)

const selectedT = ref(0)
const selectedL = ref(0)
const selectedR = ref(0)
const selectedF = ref(0)
const selectedB = ref(0)

function closeModal() {
  isOpen.value = false
}

function openModal() {
  isOpen.value = true
}

const changeData = (event) => {
  let lastIdSelected = playerCard.value.id
  let player = training.getPlayerInfo(lastIdSelected)
  if (!player) {
    training.addPLayerInfo(lastIdSelected, { 'balls': balls.value, 'name': playerCard.value.name.full })
  } else {
    if (balls.value > player.balls) {
      training.addPLayerInfo(lastIdSelected, { 'balls': balls.value, 'name': playerCard.value.name.full })
    }
  }


  balls.value = 0
  const elementID = event.target.value;
  playerCard.value = playerList.find((item) => item.id === elementID)
  dataProcess.value.player = playerCard.value.id

  player = training.getPlayerInfo(playerCard.value.id)
  if (player) {
    balls.value = player.balls
  } else {
    balls.value = 0
  }

  change.value += 1;
  velocity.value = 0
}

const addPlayer = async () => {
  isLoading.status = !isLoading.status;
  try {
    if (dataPlayer.value == undefined || dataPlayer.value === '') {
      isLoading.status = !isLoading.status;
      toast.fire({
        icon: 'warning',
        title: 'Validation',
        text: "Select one player",
      })
      return;
    } else {
      const sortValue = ref([])
      if (training.trainingActive.lineup == null || training.trainingActive.lineup == undefined || training.trainingActive.lineup.length == 0) {
        training.trainingActive.lineup = []
        sortValue.value.push(sort.value + 1)
      } else {
        training.trainingActive.lineup.forEach(element => {
          sortValue.value.push(element.sort)
        })
      }

      let dataToAdd = {
        player: dataPlayer.value,
        pitching: false,
        batting: true,
        sort: Math.max(...sortValue.value) + 1
      }
      await axiosPost(`coach/lineup/${training.trainingActive.id}`, dataToAdd).then(async (response) => {
        if (response) {
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'success',
            title: 'Save player',
            text: 'Player added',
          })
          training.trainingActive.lineup.push(response.data.data)
          training.trainingActive.players.push(response.data.data.player)
          // playerList.push(response.data.data.player)
          isOpenAdd.value = false
          training.setCountBallsTraining(balls.value)
          router.go(router.currentRoute)
          // await router.replace("/track/batting")
        }
      })
    }
  } catch (error) {
    isLoading.status = !isLoading.status;
    toast.fire({
      icon: 'error',
      title: 'Not add player',
      text: 'Sorry it is not possible save the information in this moment',
    })
  }
}

const save = async () => {
  isLoading.status = !isLoading.status;
  let dataToSave = {
    practice_id: dataProcess.value.practice,
    team_id: dataProcess.value.team,
    user_id: playerCard.value.id,
    launch_angle: cageStadistics.value.launchAngle,
    launch_angle_velocity: dataProcess.value.velocity,
    spray_angle: cageStadistics.value.sprayAngle,
    distance_travel: cageStadistics.value.distanceRaveled,
    ground_ball: cageStadistics.value.groundBall,
    cage_mark: dataProcess.value.cage_mark,
    cage_position: dataProcess.value.cage_position,
  }

  try {
    if (training.trainingActive.distance_travel != null) {
      await axiosPut('result/cage/' + training.trainingActive.id, {
        'launch_angle': dataToSave.launch_angle,
        'launch_angle_velocity': dataToSave.launch_angle_velocity,
        'spray_angle': dataToSave.spray_angle,
        'distance_travel': dataToSave.distance_travel,
        'ground_ball': dataToSave.ground_ball,
        'cage_mark': dataToSave.cage_mark,
        'cage_position': dataToSave.cage_position
      }).then(async (response) => {
        if (response) {
          isLoading.status = !isLoading.status;
          toast.fire({
            icon: 'success',
            title: 'Update training',
            text: 'Training updated',
          })
          router.push({
            path: '/training/cage'
          })
        }
      })
    } else {
      if (dataToSave.cage_position != null && dataToSave.cage_position != '') {
        await axiosPost('result/cage', dataToSave).then(async (response) => {
          if (response) {
            isLoading.status = !isLoading.status;
            toast.fire({
              icon: 'success',
              title: 'Save training',
              text: 'Training saved',
            })
          }
          balls.value++
          sort.value += 1;
          resetSelected()
          resetValues()
          velocity.value = 0

        })

      } else {
        toast.fire({
          icon: 'warning',
          title: 'Required Data',
          text: 'Please select an area of the cage',
        })
        isLoading.status = !isLoading.status;
      }
    }


  } catch (error) {
    isLoading.status = !isLoading.status;
    toast.fire({
      icon: 'error',
      title: 'Not save training',
      text: 'Sorry it is not possible save the information in this moment !!!!!!!',
    })
  }
  change.value += 1;
}

const endPractice = async () => {
  if (picked.value !== 'completed') {
    if (userData.type == "player") {
      training.addPLayerInfo(userData.id, {
        'balls': 0,
      })
    }
    training.setCountBallsTraining(0);
    await router.push('/')
  } else {
    let dataEnd = {
      end_note: endNote.value,
      is_completed: true,
    }
    try {
      if (endNote.value === '') {
        toast.fire({
          icon: 'warning',
          title: 'End training',
          text: 'The practice note is required',
        })
      } else {
        isLoading.status = !isLoading.status;
        await axiosPut('training/' + dataProcess.value.practice, dataEnd).then(async (response) => {
          if (response) {
            isLoading.status = !isLoading.status;
            toast.fire({
              icon: 'success',
              title: 'End training',
              text: 'Finished session' + dataProcess.value.practice,
            })
            let name_route = ''
            if (userData.type == "player") {
              training.addPLayerInfo(userData.id, {
                'balls': 0,
              })
              name_route = '/player-dashboard'
            } else {
              name_route = '/dashboard'
            }
            training.setCountBallsTraining(0);
            await router.push(name_route)
          }
        })
      }
    } catch (error) {
      isLoading.status = !isLoading.status;
      toast.fire({
        icon: 'error',
        title: 'End training',
        text: 'Sorry it is not possible process this information in this moment',
      })
    }
  }
}
const openStatistics = () => {
  let link = router.resolve({ name: 'training.statsCage', params: { 'idPractice': training.trainingActive.id } })
  window.open(link.href)
}

const compareListPlayers = async () => {
  const list = ref([]);
  playerList.forEach(object => {
    list.value.push(object.id)
  });
  for (let index = 0; index < players.length; index++) {
    const element = players[index];
    if (!list.value.includes(element.id)) {
      playerToAddList.value.push(element)
    }
  }
}

onMounted(() => {
  let player = training.getPlayerInfo(playerCard.value.id)
  if (player) {
    balls.value = player.balls ?? 0
  } else {
    balls.value = 0
  }

  calcular()
  velocity.value = training.trainingActive.launch_angle_velocity ?? 0
  dataProcess.value.velocity = training.trainingActive.launch_angle_velocity ?? 0
  if (training.trainingActive.cage_mark != null) {
    activeArea(training.trainingActive.cage_mark, training.trainingActive.cage_position, {
      launch: training.trainingActive.launch_angle,
      spray: training.trainingActive.spray_angle
    })
  }
  compareListPlayers()
})

const borderT = (value) => {
  let classe = ""
  if (value > 63 && value < 73) {
    classe += "border border-black border-b-red-500 border-b-4"
    if (value == selectedT.value) {
      classe += ' bg-red-500'
    }
  } else {
    classe += "border border-black"
    if (value == selectedT.value) {
      classe += ' bg-red-500'
    }
  }

  return classe
}

const borderL = (value) => {
  let classe = ""
  switch (value) {
    case 8: case 24:
    case 40: case 56:
    case 72: case 88:
    case 104:
      classe += 'border border-black border-r-red-500 border-r-4'
      if (value == selectedL.value) {
        classe += ' bg-red-500'
      }
      break;
    default:
      classe += 'border border-black'
      if (value == selectedL.value) {
        classe += ' bg-red-500'
      }
      break;
  }

  return classe
}

const borderR = (value) => {
  let classe = ""
  switch (value) {
    case 8: case 24:
    case 40: case 56:
    case 72: case 88:
    case 104:
      classe += 'border border-black border-r-red-500 border-r-4'
      if (value == selectedR.value) {
        classe += ' bg-red-500'
      }
      break;
    default:
      classe += 'border border-black'
      if (value == selectedR.value) {
        classe += ' bg-red-500'
      }
  }

  return classe
}

const borderF = (value) => {
  let classe = ""
  if (value == selectedF.value) {
    classe += ' bg-red-500'
  }

  return classe
}

const borderB = (value) => {
  let classe = ""
  if (selectedB.value == value) {
    classe += ' border-4 border-red-500'
  }

  return classe
}

const resetSelected = () => {
  selectedT.value = 0
  selectedL.value = 0
  selectedR.value = 0
  selectedF.value = 0
  selectedB.value = 0
}

const debounced = (fn, wait) => {
  let timer
  console.log('init')
  if (timer) {
    console.log('reset')
    clearTimeout(timer)
  }

  timer = setTimeout(() => {
    console.log('debounced')
    fn()
  }, wait)
}

const activeArea = (value, position, dataAngle) => {
  resetSelected();
  switch (position) {
    case "T":
      selectedT.value = value
      break;
    case "L":
      selectedL.value = value
      dataAngle.spray = dataAngle.spray < 0 ? dataAngle.spray : dataAngle.spray * -1
      break;
    case "R":
      selectedR.value = value
      dataAngle.spray = dataAngle.spray < 0 ? (dataAngle.spray) * -1 : dataAngle.spray
      break;
    case "F":
      selectedF.value = value
      break;
    case "B":
      selectedB.value = value
      dataAngle = {
        spray: 0,
        launch: 0,
        isGround: true,
      }
      break;
    default:
      resetSelected();
      break;
  }

  dataProcess.value.cage_mark = value
  dataProcess.value.cage_position = position
  dataProcess.value.data_angel = dataAngle.launch
  dataProcess.value.data_spray = dataAngle.spray

  changeValuesStadistics(dataAngle)
}


const changeValuesStadistics = (value) => {
  resetValues()
  let isGround = value.isGround ?? false
  let valueLaunch = '' + value.launch.toFixed(1)
  let valueSpray = '' + value.spray.toFixed(1)
  const valueDistance = isGround ? 0 : getDistance(value.spray, value.launch)
  cageStadistics.value.launchAngle = valueLaunch
  cageStadistics.value.sprayAngle = valueSpray
  cageStadistics.value.distanceRaveled = valueDistance
  cageStadistics.value.groundBall = isGround
}

const calcular = () => {
  calcLengthOfCage()
  const player = playerList[0]
  calcHeightCage({
    ft: player.body.ft ?? 6,
    in: player.body.inch ?? 0
  })
  calRigthLeftCellValue()
  calTopCellValue()
  calFrontCage()
}

const calcLengthOfCage = () => {
  let lastValue = 0
  for (let index = 0; index < 16; index++) {
    const valueCell = (props.lengthCage / 16) + lastValue
    lengthOfCage.value.push(valueCell)
    lastValue = valueCell
  }
}

const calcHeightCage = (playerHeight) => {
  const alturaPlayer = ((playerHeight.ft + (playerHeight.in / 12)) / 2)

  const heightCage = props.cageHeight - alturaPlayer
  heightOfCage.value.push(heightCage)

  for (let index = 4; index >= 1; index--) {
    let heightCell = heightCage * (index / 5)
    heightOfCage.value.push(heightCell)
  }

  let heightCell = heightCage * 0
  heightOfCage.value.push(heightCell)

  heightCell = 0 - alturaPlayer
  heightOfCage.value.push(heightCell)
}

const calRigthLeftCellValue = () => {
  let cells = [];

  for (const hCell of heightOfCage.value) {
    for (const wCell of lengthOfCage.value) {
      const launch = hCell / Math.sqrt((Math.pow((props.widthCage / 2), 2)) + (Math.pow(wCell, 2)))
      const spray = (props.widthCage / 2) / wCell
      cells.push(
        {
          launch: radToGrade(launch),
          spray: radToGrade(spray)
        }
      )
    }

  }

  cellsRigth.value = cells
  orderCellLeft()
}

const radToGrade = (value) => {
  return Math.atan(value) * (180 / Math.PI)
}

const orderCellLeft = () => {
  let cells = [];
  let maxPosition = 0
  for (let row = 0; row < 7; row++) {
    maxPosition = 16 * (row + 1)
    for (let index = 0; index < 16; index++) {

      cells.push(cellsRigth.value[maxPosition - 1])
      maxPosition--
    }

  }

  cellsLeft.value = cells
}


const calTopCellValue = () => {
  let cells = []
  for (const wCell of lengthOfCage.value) {
    let value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow((props.widthCage / 2), 2))))
    let value2 = 7.0 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: -radToGrade(value2)
    })

    value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow(((props.widthCage / 2) * (3 / 4)), 2))))
    value2 = 5.3 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: -radToGrade(value2)
    })

    value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow(((props.widthCage / 2) * (2 / 4)), 2))))
    value2 = 3.5 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: -radToGrade(value2)
    })

    value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow(((props.widthCage / 2) * (1 / 4)), 2))))
    value2 = 1.8 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: -radToGrade(value2)
    })

    value1 = 11.000 / wCell
    value2 = 0.0 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: radToGrade(value2)
    })

    value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow(((props.widthCage / 2) * (1 / 4)), 2))))
    value2 = 1.8 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: radToGrade(value2)
    })

    value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow(((props.widthCage / 2) * (2 / 4)), 2))))
    value2 = 3.5 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: radToGrade(value2)
    })

    value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow(((props.widthCage / 2) * (3 / 4)), 2))))
    value2 = 5.3 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: radToGrade(value2)
    })

    value1 = 11.000 / (Math.sqrt((Math.pow(wCell, 2) + Math.pow((props.widthCage / 2), 2))))
    value2 = 7.0 / wCell
    cells.push({
      launch: radToGrade(value1),
      spray: radToGrade(value2)
    })
  }

  cellsTop.value = cells
}

const calFrontCage = () => {
  const widthCage = calWidthCageBack()
  let cell = []
  let simbol = '-'
  for (const hCell of heightOfCage.value) {
    for (const wCell of widthCage) {
      let value = hCell / Math.sqrt(Math.pow(wCell, 2) + Math.pow(props.lengthCage, 2))
      let value2 = wCell / props.lengthCage
      if (value2 == 0) {
        simbol = ''
      }

      cell.push({
        launch: radToGrade(value),
        spray: simbol == '-' ? radToGrade(value2) * -1 : radToGrade(value2)
      })
    }
    simbol = '-'
  }
  cellsBack.value = cell
}

const calWidthCageBack = () => {
  const widthCage = []
  widthCage.push((props.widthCage / 2) + 0)
  widthCage.push((props.widthCage / 2) * (3 / 4) + 0)
  widthCage.push((props.widthCage / 2) * (2 / 4) + 0)
  widthCage.push((props.widthCage / 2) * (1 / 4) + 0)
  widthCage.push(0)
  widthCage.push((props.widthCage / 2) * (1 / 4) + 0)
  widthCage.push((props.widthCage / 2) * (2 / 4) + 0)
  widthCage.push((props.widthCage / 2) * (3 / 4) + 0)
  widthCage.push((props.widthCage / 2) + 0)

  return widthCage
}

const getDistance = (spray, launch) => {

  let $iVelo = dataProcess.value.velocity;
  let $iSpray = spray;
  let $iLaunch = launch;

  let $time = [];
  //distance arrays in 3 dimensions
  let $distanceR = [];
  let $distanceX = [];
  let $distanceY = [];
  let $distanceZ = [];
  let $veloX = [];
  let $veloY = [];
  let $veloZ = [];
  let $veloV = [];
  let $veloVW = [];
  let $accelX = [];
  let $accelY = [];
  let $accelZ = [];
  let $dragX = [];
  let $dragY = [];
  let $dragZ = [];
  let $magnusX = [];
  let $magnusY = [];
  let $magnusZ = [];
  //drag and lift coefficient arrays
  let $dragAndLiftS = [];  //spin
  let $dragAndLiftCD = []; //drag
  let $dragAndLiftCL = [];  //lift

  let $x0 = 0.0; //initial left/right
  let $y0 = 2.0; //initial distance
  let $z0 = 3.0; //intial height
  let $v0; //initial Velocity (total)
  let $v0x;  //initial X velo (left/right )
  let $v0y; //initial Y velo (forward)
  let $v0z = 0; //initial Z velo (height)
  let $windSpeed = 0; //wind speed
  let $windDirec = 180; // wind direction
  let $wx;
  let $wy;
  let $wz;
  let $vxw = $windSpeed * 1.467 * (Math.sin($windDirec) * Math.PI / 180);
  let $vyw = $windSpeed * 1.467 * (Math.cos($windDirec) * Math.PI / 180);
  let $vw;

  let $backspin = 2350;
  let $sidespin = 0;
  let $gyrospin = 0;

  //Other Variables and constants used in formula
  let $temp = 70;
  let $svp = 4.5841 * Math.pow(Math.E, (18.687 - $temp / 234.5) * $temp / (257.14 + $temp));
  let $humidity = 0;
  let $pressure = 30.02;
  let $elev = 0;
  let $beta = 0.0001217;
  let $slRho = 1.2929 * (273 / ($temp + 273) * ($pressure * Math.pow(Math.E, -1 * $beta * $elev) - .3783 * $humidity * $svp / 100) / 760);
  let $rho = .0621 * $slRho;

  let $c0 = .07182 * $rho;
  let $cons = 0.005310;
  let $dt = 0.01;    // delta time
  let $dtPerSec = 1;
  let $omega = Math.sqrt(Math.pow($backspin, 2) + Math.pow($sidespin, 2)) * (Math.PI / 30);
  let $romega = $omega * 0.1210240713;
  let $cl0 = 0.583;
  let $cl1 = 2.333;
  let $cl2 = 1.120;
  let $tauSec = 30;
  let $cdSpin = .029;
  let $spin = Math.sqrt(Math.pow($backspin, 2) + Math.pow($sidespin, 2));
  let $cd0 = 0.3;  //drag coefficient for a baseball
  let $flightDist = 0;



  //intialize time Array not including wind
  for (let $i = 0; $i < 999; $i++) {
    $time[$i] = .01 * $i;
  }

  //intial velocities assigned to arrays
  $v0 = $iVelo * 1.467;
  $veloX[0] = 1.467 * $iVelo * Math.cos($iLaunch * Math.PI / 180) * Math.sin($iSpray * Math.PI / 180);
  $veloY[0] = 1.467 * $iVelo * Math.cos($iLaunch * Math.PI / 180) * Math.cos($iSpray * Math.PI / 180);
  $veloZ[0] = 1.467 * $iVelo * Math.sin($iLaunch * Math.PI / 180);
  $veloV[0] = Math.sqrt(Math.pow($veloX[0], 2) + Math.pow($veloY[0], 2) + Math.pow($veloZ[0], 2));
  $veloVW[0] = Math.sqrt(Math.pow($veloX[0] - $vxw, 2) + Math.pow($veloY[0] - $vyw, 2) + Math.pow($veloZ[0], 2));;

  //initial velocities with respect to wind
  $vw = Math.sqrt(Math.pow($veloX[0] - $vxw, 2) + Math.pow($veloY[0] - $vyw, 2) + ($veloZ[0] * $veloZ[0]));
  $wx = ($backspin * Math.cos($iSpray * Math.PI / 180) - $sidespin * Math.sin($iLaunch * Math.PI / 180) * Math.sin($iSpray * Math.PI / 180) + $gyrospin * $veloZ[0] / $v0) * Math.PI / 30;
  $wy = (-$backspin * Math.sin($iSpray * Math.PI / 180) - $sidespin * Math.sin($iLaunch * Math.PI / 180) * Math.cos($iSpray * Math.PI / 180) + $gyrospin * $veloZ[0] / $v0) * Math.PI / 30;
  $wz = ($sidespin * Math.cos($iLaunch * Math.PI / 180) + $gyrospin * $v0z / $v0) * Math.PI / 30;

  //inital drag and lift coefficients assigned to arrays
  $dragAndLiftS[0] = ($romega / $veloVW[0]) * Math.pow(Math.E, (-1 * $time[0]) / ($tauSec * (146.7 / $veloVW[0])));
  $dragAndLiftCD[0] = $cd0 + (($cdSpin * $spin) / 1000) * Math.pow(Math.E, -1 * $time[0] / (146.7 * ($tauSec / $veloVW[0])));
  $dragAndLiftCL[0] = $cl2 * ($dragAndLiftS[0] / ($cl0 + ($cl1 * $dragAndLiftS[0])));

  //intial drag assignments
  $dragX[0] = -1 * $cons * $dragAndLiftCD[0] * $veloVW[0] * ($veloX[0] - $vxw);
  $dragY[0] = -1 * $cons * $dragAndLiftCD[0] * $veloVW[0] * ($veloY[0] - $vyw);
  $dragZ[0] = -1 * $cons * $dragAndLiftCD[0] * $veloVW[0] * ($veloZ[0]);

  //initial magnus force assignments
  $magnusX[0] = $cons * ($dragAndLiftCL[0] / $omega) * $veloVW[0] * ($wy * $veloZ[0] - $wz * ($veloY[0] - $vyw));
  $magnusY[0] = $cons * ($dragAndLiftCL[0] / $omega) * $veloVW[0] * ($wz * ($veloX[0] - $vxw) - $wx * $veloZ[0]);
  $magnusZ[0] = $cons * ($dragAndLiftCL[0] / $omega) * $veloVW[0] * ($wx * ($veloY[0] - $vyw) - $wy * ($veloX[0] - $vxw));

  //acceleration by dimension
  $accelX[0] = $dragX[0] + $magnusX[0];
  $accelY[0] = $dragY[0] + $magnusY[0];
  $accelZ[0] = $dragZ[0] + $magnusZ[0] - 32.174;

  //initial distances
  $distanceX[0] = $x0;
  $distanceY[0] = $y0;
  $distanceZ[0] = $z0;
  $distanceR[0] = Math.sqrt(Math.pow($distanceX[0], 2) + Math.pow($distanceY[0], 2));


  for (let $i = 1; $i < 999; $i++) {
    $veloX[$i] = $veloX[$i - 1] + $dt * $accelX[$i - 1];
    $veloY[$i] = $veloY[$i - 1] + $dt * $accelY[$i - 1];
    $veloZ[$i] = $veloZ[$i - 1] + $dt * $accelZ[$i - 1];
    $veloV[$i] = Math.sqrt(Math.pow($veloX[$i], 2) + Math.pow($veloY[$i], 2) + Math.pow($veloZ[$i], 2));
    $veloVW[$i] = Math.sqrt(Math.pow(($veloX[$i] - $vxw), 2) + Math.pow(($veloY[$i] - $vyw), 2) + Math.pow($veloZ[$i], 2));

    $dragAndLiftS[$i] = ($romega / $veloVW[$i]) * Math.pow(Math.E, -1 * $time[$i] / ($tauSec * (146.7 / $veloVW[$i])));
    $dragAndLiftCD[$i] = $cd0 + (($cdSpin * $spin) / 1000) * Math.pow(Math.E, -1 * $time[$i] / (146.7 * ($tauSec / $veloVW[$i])));
    $dragAndLiftCL[$i] = $cl2 * ($dragAndLiftS[$i] / ($cl0 + $cl1 * $dragAndLiftS[$i]));

    $dragX[$i] = -1 * $cons * $dragAndLiftCD[$i] * $veloVW[$i] * ($veloX[$i] - $vxw);
    $dragY[$i] = -1 * $cons * $dragAndLiftCD[$i] * $veloVW[$i] * ($veloY[$i] - $vyw);
    $dragZ[$i] = -1 * $cons * $dragAndLiftCD[$i] * $veloVW[$i] * ($veloZ[$i]);

    $magnusX[$i] = $cons * ($dragAndLiftCL[$i] / $omega) * $veloVW[$i] * ($wy * $veloZ[$i] - $wz * ($veloY[$i] - $vyw));
    $magnusY[$i] = $cons * ($dragAndLiftCL[$i] / $omega) * $veloVW[$i] * ($wz * ($veloX[$i] - $vxw) - $wx * $veloZ[$i]);
    $magnusZ[$i] = $cons * ($dragAndLiftCL[$i] / $omega) * $veloVW[$i] * ($wx * ($veloY[$i] - $vyw) - $wy * ($veloX[$i] - $vxw));

    $accelX[$i] = $dragX[$i] + $magnusX[$i];
    $accelY[$i] = $dragY[$i] + $magnusY[$i];
    $accelZ[$i] = $dragZ[$i] + $magnusZ[$i] - 32.174;

    $distanceX[$i] = $distanceX[$i - 1] + $veloX[$i - 1] * $dt + 0.5 * $accelX[$i - 1] * $dt * $dt;
    $distanceY[$i] = $distanceY[$i - 1] + $veloY[$i - 1] * $dt + 0.5 * $accelY[$i - 1] * $dt * $dt;
    $distanceR[$i] = Math.sqrt(Math.pow($distanceX[$i], 2) + Math.pow($distanceY[$i], 2));
    $distanceZ[$i] = $distanceZ[$i - 1] + $veloZ[$i - 1] * $dt + 0.5 * $accelZ[$i - 1] * $dt * $dt;

    if ($distanceZ[$i] <= 0) {
      $flightDist = ($distanceR[$i - 1] + $distanceR[$i]) / 2;
      break;
    }
  }

  // cageStadistics.value.distanceRaveled = Math.round($flightDist,2);
  return Math.round($flightDist, 2);
}

const resetValues = () => {
  cageStadistics.value.launchAngle = 0
  cageStadistics.value.sprayAngle = 0
  cageStadistics.value.distanceRaveled = 0
  cageStadistics.value.groundBall = false
}

const activeDebounceArea = () => {
  activeArea(dataProcess.value.cage_mark, dataProcess.value.cage_position, {
    launch: dataProcess.value.data_angel,
    spray: dataProcess.value.data_spray
  })
}

watch(velocity, (after, before) => {
  dataProcess.value.velocity = Number.parseInt(after)
})
</script>
<template>
  <Loader v-show="!isLoading.status" />
  <Layout>

    <div class="grid grid-cols-12 gap-4 mt-4" :class="userData.type == 'player' ? 'pt-14' : ''">
      <div class="flex-row col-span-12 m-4 xl:col-span-6">
        <!--Top panel-->
        <div class="grid grid-cols-3">
          <div></div>
          <div class="grid grid-cols-9 top-panel">
            <div class="cell" v-for="tn in 144" :key="tn" @click.debounce.750="activeArea(tn, 'T', cellsTop[tn - 1])"
              :class="borderT(tn)">

            </div>
          </div>
        </div>
        <!--center panels-->
        <div class="grid grid-cols-3">
          <div class="grid grid-cols-[repeat(16,1fr)] gap-0 left-panel">
            <div class="cell" v-for="ln in 112" :key="ln" @click.debounce.750="activeArea(ln, 'L', cellsRigth[ln - 1])"
              :class="borderL(ln)">

            </div>
          </div>
          <div class="grid grid-cols-9 gap-0 front-panel">
            <div class="cell-border" v-for="fn in 63" @click.debounce.750="activeArea(fn, 'F', cellsBack[fn - 1])"
              :class="borderF(fn)">

            </div>
          </div>
          <div class="grid grid-cols-[repeat(16,1fr)] gap-0 right-panel">
            <div class="cell" v-for="rn in 112" :key="rn" @click.debounce.750="activeArea(rn, 'R', cellsLeft[rn - 1])"
              :class="borderR(rn)">
            </div>
          </div>
        </div>
        <!--botom panel-->
        <div class="grid grid-cols-6 mx-40">
          <div>

          </div>
          <div class="col-span-4 bg-green-900 h-80 bottom-panel " @click.debounce.750="activeArea(1, 'B', {})"
            :class="borderB(1)">

          </div>
          <div>

          </div>
        </div>
      </div>
      <div class="grid grid-cols-12 col-span-12 xl:col-span-6">
        <div class="grid justify-center col-span-12">
          <div
            class="flex flex-col md:flex-row gap-2 lg:gap-4 justify-evenly lg:justify-start h-auto md:h-[6em] lg:h-[9em] bg-white rounded-lg">
            <div class="flex self-center">

              <CageIcon class="p-4" color="082247" height="75" width="75" />

              <div class="lg:ml-5">
                <h3 class="text-fungo-red  text-[0.6em] lg:text-[1.2em] fungo-700  mt-4 lg:mt-2">Cage
                  practice</h3>
                <h1 class="text-fungo-darkblue text-[1.2em] font-fungo-800 lg:text-[1.7em] lg:-mt-2">Hitter</h1>
              </div>
            </div>
            <div
              class="border bg-fungo-gray7 grid grid-cols-1 lg:grid-cols-2 min-w-[150px] w-[150px] lg:w-[280px] lg:min-w-[270px] content-center justify-evenly">
              <div class="hidden ml-3 lg:inline-flex ">
                <img :src="playerCard.picture ? playerCard.picture : DefaultImg" alt="" class="img-player">
              </div>
              <div
                class="flex flex-col justify-center gap-x-2 mx-auto text-fungo-darkblue font-fungo-400 text-[16px] lg:w-[165px] lg:-ml-10">
                <div class="font-fungo-800">{{ playerCard.name.full }}</div>
                <div>Jersey: <span class="text-fungo-red font-fungo-800">{{ playerCard.shirt_number }}</span></div>
              </div>
            </div>
            <!-- <div v-if="training.trainingActive.players.length > 1 "
                  class="grid content-center">
                <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[195px] px-3">Change player</div>
                <select class="text-[12px] w-[90%]" @change="changeData($event)">
                  <option v-for="player in playerList " :value="player.id">{{ player.name.full }}</option>
                </select>
              </div> -->
            <template v-if="userData.type !== 'player' && training.trainingActive.cage_mark == null">
              <div v-if="training.trainingActive.players.length > 1"
                class="flex flex-row gap-0 lg:flex-col lg:gap-4 lg:self-center">
                <div>
                  <div class="text-fungo-darkblue font-fungo-800 text-[16px] lg:w-[200px]">Change player</div>
                  <select class="text-[12px] w-[90%] rounded-lg" @change="changeData($event)">
                    <option v-for="player in playerList " :value="player.id">{{ player.name.full }}</option>
                  </select>
                </div>
                <div class="flex self-center lg:justify-center">
                  <div @click="isOpenAdd = true" class="text-white bg-fungo-darkblue font-fungo-800 text-center
                      py-2 rounded-2xl cursor-pointer text-[16px] max-w-[150px] px-4">
                    Add Player
                  </div>
                </div>
              </div>
              <div v-else class="grid content-center mr-10">
                <div @click="isOpenAdd = true" class="text-white bg-fungo-darkblue font-fungo-800 text-center
                      py-2 rounded-2xl cursor-pointer text-[16px] max-w-[150px] px-4">
                  Add Player
                </div>
              </div>
            </template>
          </div>
          <div class="order-1 w-full mt-5 xl:col-span-5">

            <div v-if="training.trainingActive.cage_mark == null" class="grid grid-cols-3 text-[16px] text-center gap-1">
              <div class="relative grid grid-cols-2 bg-white shadow-lg balls-count">
                <div class="grid items-center text-fungo-red font-fungo-300 text-[16px]">Total swings
                  :
                </div>
                <div class="ball-number grid items-center justify-items-end h-[90%]
              justify-evenly  text-fungo-darkblue font-fungo-800 text-[1.5em] ">
                  {{ balls }}

                </div>
              </div>
              <div class="grid content-center text-[16px] xl:text-[24px] text-fungo-blue focus:accent-green-500">
                <button @click="openStatistics">
                  show statistics
                  <svg class="inline fill-fungo-blue" height="20" viewBox="0 0 40 41" width="20"
                    xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd"
                      d="M8.64783 8.99553V31.9497H31.602V20.4726h3.2792v11.4771c0 1.8036-1.4756 3.2792-3.2792 3.2792H8.64783c-1.81995 0-3.27918-1.4756-3.27918-3.2792V8.99553c0-1.80355 1.45923-3.27918 3.27918-3.27918H20.1249v3.27918H8.64783zm14.75907 0V5.71635H34.884V17.1935h-3.2791v-5.8862L15.4877 27.4245l-2.3118-2.3118L29.293 8.99553h-5.8861z"
                      fill-rule="evenodd" />
                  </svg>
                </button>
              </div>
              <div class="grid content-center text-[16px] xl:text-[24px] text-fungo-blue">
                <button @click="openModal">end practice ></button>
              </div>
            </div>

          </div>
        </div>
        <div class="flex items-center col-span-12 md:col-span-6">
          <div class="w-[80%]">
            <h3 class="font-fungo-500">Cage Statistics</h3>
            <div class="flex items-center justify-between pl-4 mb-2 align-middle bg-white">
              <span>
                Distance traveled
              </span>
              <span class="p-4 bg-gray-100">
                {{ cageStadistics.distanceRaveled }}
              </span>
            </div>
            <div class="flex items-center justify-between pl-4 mb-2 align-middle bg-white">
              <span>
                Launch angle
              </span>
              <span class="p-4 bg-gray-100">
                {{ cageStadistics.launchAngle }}
              </span>
            </div>
            <div class="flex items-center justify-between pl-4 mb-2 align-middle bg-white">
              <span>
                Spray angle
              </span>
              <span class="p-4 bg-gray-100">
                {{ cageStadistics.sprayAngle }}
              </span>
            </div>
            <div class="flex items-center justify-between pl-4 mb-2 align-middle bg-white">
              <span>
                Ground ball
              </span>
              <span class="p-4 bg-gray-100">
                {{ cageStadistics.groundBall }}
              </span>
            </div>
          </div>
        </div>
        <div class="col-span-12 p-4 bg-white md:col-span-6">
          <div class="text-center text-fungo-blue2 font-fungo-500">Velocity</div>
          <VelocityInput :key="change" v-model="velocity" v-on:eventChange="debounced(activeDebounceArea, 600)">
          </VelocityInput>
          <div class="grid mt-5">
            <button class="mx-auto text-white border rounded-xl rounded-l-3xl bg-fungo-darkblue" @click="save()">
              <div class="grid grid-cols-2 w-[200px]">
                <div class="p-1 m-1"><img class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                    src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                <div class="grid content-center items-center mr-8 text-[20px] -ml-12">{{ training.trainingActive.cage_mark
                  != null ? 'Changed' : 'Save' }}</div>
              </div>
            </button>
          </div>
        </div>
      </div>

    </div>

    <div v-if="isOpenAdd">
      <div class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[30%] max-h-[35%]
          lg:min-h-[30%] lg:max-h-[35%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
          <div>
            <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
              <h1 class="my-5 text-lg lg:text-2xl text-fungo-red font-fungo-700">Add player</h1>
              <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]"
                @click="isOpenAdd = false">
                <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
              </div>
            </div>
          </div>
          <div class="bg-fungo-gray2 mb-5 py-10 px-[3%]">
            <form action="" name="add-player" class="grid tems-center w-[95%] lg:w-[100%]">
              <select class="text-[16px] w-[100%] rounded-lg" v-model="dataPlayer">
                <option value="" disabled selected>Select one player</option>
                <option v-for="player in playerToAddList " :value="player.id">{{ player.name.full }}</option>
              </select>
            </form>
          </div>
          <div class="flex flex-row justify-center">
            <div class="justify-center">
              <button @click="addPlayer()" class="grid place-items-center grid-flow-col flex-row rounded-xl w-[200px] lg:w-[250px]
                  px-2 py-1 text-xl md:text-[12px] lg:text-[16px] bg-fungo-red text-white hover:bg-fungo-red-hover"
                type="submit">
                Add
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="fixed inset-0 z-40 opacity-70 bg-fungo-darkblue"></div>
    </div>

    <TransitionRoot :show="isOpen" appear as="template">
      <Dialog as="div" class="relative z-10" @close="closeModal">
        <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100"
          leave="duration-200 ease-in" leave-from="opacity-100" leave-to="opacity-0">
          <div class="fixed inset-0 bg-black bg-opacity-25" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex items-center justify-center min-h-full p-4 text-center">
            <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100" leave="duration-200 ease-in" leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95">
              <DialogPanel
                class="w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                <DialogTitle as="h2" class="mb-3 text-2xl font-medium leading-6 text-fungo-red">
                  End Batting Practice
                </DialogTitle>
                <div class="container">
                  <div class="grid grid-flow-row gap-3">
                    <div class="grid grid-cols-3 p-5 border bg-fungo-gray4 place-items-center">
                      <div>
                        <svg class="w-[40px] h-[40px]" fill="none" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                          <path clip-rule="evenodd"
                            d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z"
                            fill="#082247" fill-rule="evenodd" />
                          <path
                            d="M37 39C37 37.1435 36.2625 35.363 34.9497 34.0503C33.637 32.7375 31.8565 32 30 32C28.1435 32 26.363 32.7375 25.0503 34.0503C23.7375 35.363 23 37.1435 23 39"
                            stroke="#082247" stroke-linecap="round" stroke-width="4" />
                        </svg>
                      </div>
                      <div class="grid gap-1 grid-flow-row3">
                        <div class="text-sm text-fungo-blue font-fungo-700">Status</div>
                        <div class="text-fungo-darkblue font-fungo-700 "> In progress</div>
                        <div>
                          <progress class="rounded overflow-hidden h-[7px] in-proress w-[70px]" max="100" value="50">
                          </progress>
                        </div>
                      </div>
                      <div><input v-model="picked" checked class="h-[30px] w-[30px]" name="end-session" type="radio"
                          value="progress"></div>
                    </div>
                    <div class="grid grid-cols-3 p-5 border bg-fungo-gray4 place-items-center">
                      <div>
                        <svg class="w-[40px] h-[40px]" fill="none" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                          <path clip-rule="evenodd"
                            d="M5 30C5 16.2 16.175 5 29.975 5C43.8 5 55 16.2 55 30C55 43.8 43.8 55 29.975 55C16.175 55 5 43.8 5 30ZM10 30C10 41.05 18.95 50 30 50C41.05 50 50 41.05 50 30C50 18.95 41.05 10 30 10C18.95 10 10 18.95 10 30ZM38.75 27.5C40.825 27.5 42.5 25.825 42.5 23.75C42.5 21.675 40.825 20 38.75 20C36.675 20 35 21.675 35 23.75C35 25.825 36.675 27.5 38.75 27.5ZM25 23.75C25 25.825 23.325 27.5 21.25 27.5C19.175 27.5 17.5 25.825 17.5 23.75C17.5 21.675 19.175 20 21.25 20C23.325 20 25 21.675 25 23.75Z"
                            fill="#082247" fill-rule="evenodd" />
                          <path
                            d="M23 33C23 34.8565 23.7375 36.637 25.0503 37.9497C26.363 39.2625 28.1435 40 30 40C31.8565 40 33.637 39.2625 34.9497 37.9497C36.2625 36.637 37 34.8565 37 33"
                            stroke="#082247" stroke-linecap="round" stroke-width="4" />
                        </svg>
                      </div>
                      <div class="grid gap-1 grid-flow-row3">
                        <div class="text-sm text-fungo-blue font-fungo-700">Status</div>
                        <div class="text-fungo-darkblue font-fungo-700 "> Completed</div>
                        <div>
                          <progress class="rounded overflow-hidden h-[7px] completed w-[70px]" max="100" value="100">
                          </progress>
                        </div>
                      </div>
                      <div><input v-model="picked" class="h-[30px] w-[30px]" name="end-session" type="radio"
                          value="completed"></div>
                    </div>
                  </div>
                  <div v-show="picked === 'completed'" class="grid gap-1 mt-3 grid-flow">End
                    Note<textarea v-model="endNote"></textarea></div>
                  <div class="grid mt-5">
                    <button class="mx-auto text-white border rounded-xl rounded-l-3xl bg-fungo-red" @click="endPractice">
                      <div class="grid grid-cols-2 w-[200px]">
                        <div class="p-1 m-1"><img class="w-[20px] h-[20px] xl:w-[30px] xl:h-[30px]"
                            src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                        <div class="grid content-center items-center mr-8 text-[16px] font-fungo-700 -ml-12">Finish
                          Training
                        </div>
                      </div>
                    </button>
                  </div>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </Layout>
</template>
<style scoped>
.cell {
  @apply h-[1em];
  cursor: pointer;
}

.cell-border {
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

.dash-table-container {
  position: relative;
  left: 0;
}

.box-input-col {
  @apply flex flex-col w-[100%];
}

.dash-body {
  @apply h-full w-full flex flex-col justify-between;
}

.capitalize {
  text-transform: capitalize;
}

.img-size {
  @apply w-[18em] h-[22.1em];
}

.ct * {
  border: 1px solid #1a73e8;
}

.img-player {
  height: 75px;
  width: 75px;
  border: 5px solid #d9d9d9;
  border-radius: 100px;
}

.active-btn-trajectory,
.active-btn-contact {
  @apply bg-fungo-darkblue text-white;
}

.balls-count {
  border-left: solid red 2px;
  border-bottom-right-radius: 10px;
  border-top-right-radius: 10px;
}

.ball-number {
  @apply bg-fungo-gray7;
  border-bottom-right-radius: 10px;
  border-top-right-radius: 10px;
}

.button-ct {
  @apply border border-fungo-darkblue rounded h-[2em] w-[5em] focus:bg-fungo-darkblue focus:text-white;
}

progress.in-proress::-webkit-progress-value {
  background: #FFB457;
}

progress.completed::-webkit-progress-value {
  background: #35A800;
}

progress::-webkit-progress-bar {
  background: #DBDFF1;
}
</style>
