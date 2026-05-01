
const firtstTabHeading = ['Overall','BP','Training EV', 'Cage mode','LiveAB']
const secondTabHeading = ['Overall','BP','LiveAB']
const thirdTabHeading = ['3oz','4oz','5oz','6oz','7oz']
const fourthTabHeading = ['1 hop','2 hops','3 hops']
const fifthTabHeading = ['Overall','LongToss','WeighBall']
const sixthTabHeading = ['Power Clean','Bench Press','Front Squat', 'Back Squat', 'Dead Lift']
const seventhTabHeading = ['(MaxPC)/BW','(MaxBP)/BW','(MaxFS)/BW', '(MaxBS)/BW', '(MaxDL)/BW']

const optNameVeloDate = [
  {
    title: 'N°.',
    value: '1',
    slot: 'num'
  },
  {
    title: 'Player Name',
    value: 'name',
    slot: 'name',
  },
  {
    title: 'Velocity',
    value: 'velocity',
    slot: 'velocity',
  },
  {
    title: 'Updated at',
    value: 'date',
    slot: 'date'
  }
]

const optNameDistanceDate = [
  {
    title: 'N°.',
    value: '1',
    slot: 'num'
  },
  {
    title: 'Player Name',
    value: 'name',
    slot: 'name',
  },
  {
    title: 'Distance',
    value: 'distance',
    slot: 'distance',
  },
  {
    title: 'Updated at',
    value: 'date',
    slot: 'date'
  }
]

const optNameAvg = [
  {
    title: 'N°.',
    value: '1',
    slot: 'num'
  },
  {
    title: 'Player Name',
    value: 'name',
    slot: 'name',
  },
  {
    title: 'AVG VELO',
    value: 'avg',
    slot: 'avg'
  }
]

const optNameCount = [
  {
    title: 'N°.',
    value: '1',
    slot: 'num'
  },
  {
    title: 'Player Name',
    value: 'name',
    slot: 'name',
  },
  {
    title: 'Count',
    value: 'count',
    slot: 'count'
  }
]

const optNamePowerDate = [
  {
    title: 'N°.',
    value: '1',
    slot: 'num'
  },
  {
    title: 'Player Name',
    value: 'name',
    slot: 'name',
  },
  {
    title: 'POWER CLEAN',
    value: 'value',
    slot: 'value'
  },
  {
    title: 'Updated at',
    value: 'dated',
    slot: 'dated'
  }
]

const optNamePowerBodyDate = [
  {
    title: 'N°.',
    value: '1',
    slot: 'num'
  },
  {
    title: 'Player Name',
    value: 'name',
    slot: 'name',
  },
  {
    title: 'BODY WEIGHT',
    value: 'value',
    slot: 'value'
  },
  {
    title: 'Updated at',
    value: 'dated',
    slot: 'dated'
  }
]

const selectOption = [
  { text: 'Max Exit Velocities', value: 1 },
  { text: 'Average Exit Velocities', value: 2 },
  { text: 'Total Swings Taken', value: 3 },
  { text: 'Max Pitching Velocites', value: 4 },
  { text: 'Average Pitching Velocites', value: 5 },
  { text: 'Total Pitches Thrown', value: 6 },
  { text: 'Weighted Balls', value: 7 },
  { text: 'Long Toss', value: 8 },
  { text: 'Total Arm Care Throws', value: 9 },
  { text: 'Weight Metrics', value: 10 },
  { text: 'Adjusted Weight Metrics', value: 11 },
]

const timerOption = [
  { text: 'All Time', value: 0 },
  { text: 'One Year', value: 12 },
  { text: 'One Month', value: 6 },
  { text: 'One Week', value: 3 }
]

export {
  optNameVeloDate,
  optNameDistanceDate,
  optNameAvg,
  optNameCount,
  optNamePowerDate,
  optNamePowerBodyDate,

  firtstTabHeading,
  secondTabHeading,
  thirdTabHeading,
  fourthTabHeading,
  fifthTabHeading,
  sixthTabHeading,
  seventhTabHeading,

  selectOption,
  timerOption
}