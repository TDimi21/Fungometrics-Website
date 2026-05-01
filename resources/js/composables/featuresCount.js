
export const useFetaturesCount = () => {

  /* hitter basic */
  const getAtBat = (item) => {
    return getPlateAppearance(item) - (getBaseOnBalls(item) + getHiyByPitch(item))
  }

  const getTotalBases = (items) => {
    return items.reduce((acumulator, currentValue) => acumulator + currentValue.bases, 0)
  }

  const getPlateAppearance = (items) => {
    return items.filter(batter => batter.turn_pitches === 0).length
  }

  const getNumberOfBases = (items, bases) => {
    return items.filter(batter => batter.bases === bases).length
  }

  const getHomeRun = (items) => {
    return items.filter(batter => batter.bases === 4).length
  }

  const getStrikeOuts = (items) => { /* total count of k */
    return items.filter(batter => batter.turn_strike === 3).length
  }

  const getBaseOnBalls = (items) => {
    return items.filter(batter => batter.turn_ball === 4).length
  }

  const getHiyByPitch = (items) => {
    return items.filter(batter => batter.pitching.trajectory == 'HBP').length
  }

  const getHits = (items) => {
    return items.filter(batter => batter.is_hit == true).length
  }

  const getBattingAverage = (items) => {
    let result = (getHits(items) / getPlateAppearance(items)).toFixed(3)

    if (isNaN(result)) {
      return (0).toFixed(3)
    } else {
      return result
    }
  }

  const getOnBasePercentage = (items) => {
    return ( getHits(items) + getBaseOnBalls(items) + getHiyByPitch(items) / (getPlateAppearance(items) - (getBaseOnBalls(items) + getHiyByPitch(items))) ).toFixed(3)
  }

  const getSluggingPercentage = (items) => {
    let result = (getTotalBases(items) / getAtBat(items)).toFixed(3)

    if (isNaN(result)) {
      return (0).toFixed(3)
    } else {
      return result
    }
  }

  const getOnBasePlusSlugging = (items) => {
    return parseFloat(getOnBasePercentage(items)) + parseFloat(getSluggingPercentage(items))
  }
  /* end hitter basic */

  /* advance hitter */
  const getPlateAppearanceWalk = (items) => {
    if (parseFloat(getBaseOnBalls(items)) == 0) {
      return 0
    } else {
      return (parseFloat(getPlateAppearance(items)) / parseFloat(getBaseOnBalls(items))).toFixed(3)
    }
  }

  const getWalksSrikeouts = (items) => {
    if (parseFloat(getStrikeOuts(items)) == 0) {
      return (0).toFixed(3)
    } else {
      return (parseFloat(getBaseOnBalls(items)) / parseFloat(getStrikeOuts(items))).toFixed(3)
    }
  }

  const getContactPercentage = (items) => {
    return ((getAtBat(items) - getStrikeOuts(items)) / getAtBat(items)).toFixed(3)
  }

  const getExtraBaseHits = (items) => {
    let acumulator = 0
    items.forEach(batter => {
      if (batter.bases !== 1) {
        acumulator = acumulator + batter.bases
      }
    });
    return acumulator
  }

  const getSixMore = (items) => {
    return items.filter(batter => batter.turn >= 6).length
  }

  const getSixMorePercentage = (items) => {
    if (getSixMore(items) == 0) {
      return 0
    } else {
      return (getSixMore(items) / getPlateAppearance(items)).toFixed(2)
    }
  }

  const getHardHit = (items) => {
    return (items.filter(batter => batter.batting.quality_of_contact == 'H').length / 3).toFixed(3)
  }

  const getQualityContactPercentage = (items, contact) => {
    let condition = items.filter(batter => batter.batting.type_of_hit == contact).length
    let lineDrive = items.filter(batter => batter.batting.type_of_hit == 'LD').length
    let groundBall = items.filter(batter => batter.batting.type_of_hit == 'GB').length
    let popFly = items.filter(batter => batter.batting.type_of_hit == 'PF').length

    let finalValue = ((condition / (lineDrive + groundBall + popFly)) * 100).toFixed(2)

    if (isNaN(finalValue)) {
      return 0.000
    } else {
      return finalValue
    }
  }

  const getBABIP = (items) => {
    let acumulator
    items.filter(batter => {
      if (batter.bases >= 1 && batter.bases <= 3) {
        acumulator++
      }
    })

    let finalResult = (acumulator / (getAtBat(items) - getStrikeOuts(items) - getHomeRun(items))).toFixed(3)

    if (isNaN(finalResult)) {
      return 0.000
    } else {
      return finalResult
    }
  }

  const getPsPa = (items) => {
    return (items.length / getPlateAppearance(items)).toFixed(3)
  }

  const get2StrikeAvg = (items) => {
    let hitsWith2K = 0
    let absWith2k = 0
    let result = 0
    let strikeCount = items.filter(batter => batter.is_strike == true).length

    items.forEach(batter => {
      if (batter.bases >= 1 && batter.bases <= 4 && strikeCount == 2) {
        hitsWith2K++
      }

      if (batter.bases >= 0 && batter.bases <= 4 && strikeCount == 2) {
        absWith2k++
      }
    });

    result = hitsWith2K / absWith2k

    if (isNaN(result)) {
      return 0
    } else {
      return (result).toFixed(2)
    }
  }

  return {
    getAtBat,
    getTotalBases,
    getPlateAppearance,
    getHomeRun,
    getNumberOfBases,
    getStrikeOuts,
    getBaseOnBalls,
    getHiyByPitch,
    getHits,
    getBattingAverage,
    getOnBasePercentage,
    getSluggingPercentage,
    getOnBasePlusSlugging,
    getPlateAppearanceWalk,
    getWalksSrikeouts,
    getContactPercentage,
    getExtraBaseHits,
    getSixMore,
    getSixMorePercentage,
    getHardHit,
    getQualityContactPercentage,
    getBABIP,
    getPsPa,
    get2StrikeAvg
  }

}