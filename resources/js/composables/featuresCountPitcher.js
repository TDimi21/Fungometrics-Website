
export const useFeaturesCountPitcher = () => {

  /* basic */
  const getTotalPitches = (items) => {
    return items.length
  }

  const getBF = (items) => {
    return items.filter(pitcher => pitcher.turn_pitches == 0).length
  }

  const getBall = (items) => {
    return items.filter(pitcher => pitcher.is_ball == true).length
  }

  const getStrike = (items) => {
    return items.filter(pitcher => pitcher.is_strike == true).length
  }

  const getStrikePercentage = (items) => {
    let result = getStrike(items) / getTotalPitches(items)

    return (result * 100).toFixed(2)
  }

  const getFB = (items) => {
    return items.filter(pitcher => pitcher.pitching.type_throw == 'FB').length
  }

  const getFBPercentage = (items) => {
    let resutl = getStrike(items) / getFB(items)

    if (isNaN(resutl)) {
      return 0.00
    } else {
      return (resutl * 100).toFixed(2)
    }
  }

  const getCH = (items) => {
    return items.filter(pitcher => pitcher.pitching.type_throw == 'CH').length
  }

  const getCHPercentage = (items) => {

    let result = getStrike(items) / getCH(items)

    return (result * 100).toFixed(2)
  }

  const getCV = (items) => {
    return items.filter(pitcher => pitcher.pitching.type_throw == 'CV').length
  }

  const getCVPercentage = (items) => {
    let result = getStrike(items) / getCV(items)

    if (isNaN(result)) {
      return 0.00
    } else {
      return (result * 100).toFixed(2)
    }
  }

  const getSL = (items) => {
    return items.filter(pitcher => pitcher.pitching.type_throw == 'SL').length
  }

  const getSLPercentage = (items) => {
    let result = getStrike(items) / getCV(items)

    if (isNaN(result)) {
      return 0.00
    } else {
      return (result * 100).toFixed(2)
    }
  }

  const getOther = (items) => {
    return items.filter(pitcher => pitcher.pitching.type_throw == 'SL').length
  }

  const getOtherPercentage = (items) => {
    let result = getStrike(items) / getOther(items)

    if (isNaN(result)) {
      return 0.00
    } else {
      return (result * 100).toFixed(2)
    }
  }


  /* ** Advance ** */
  const getPBF = (items) => {
    return (getTotalPitches(items) / getBF(items)).toFixed(2)
  }

  const getMinorOrEqualThree = (items) => {
    let counter = 0
    let apperance = 0

    items.forEach(pitching => {

      if (pitching.turn_pitches == 0 && pitching.turn <= 3) {
        counter++
      }

      if (pitching.turn_pitches == 0) {
        apperance++
      }
    });

    return (counter / apperance).toFixed(2)
  }

  const getFPSPercentage = (items) => {
    let counter = 0

    items.forEach(pitching => {
      
      if (pitching.is_strike || pitching.turn_pitches == 0) {
        counter++
      }

    });

    return ((counter / getBF(items)) * 100).toFixed(2)
  }

  const getWeakPercentage = (items) => {
    let weak = items.filter(pitcher => pitcher.batting.quality_of_contact == 'W').length

    let result = (weak / getBF(items)) * 100

    return (result).toFixed(2)
  }

  const getKBFPercentage = (items) => {
    let strikeuots = items.filter(batter => batter.turn_strike === 3).length

    let result = strikeuots / getBF(items)

    return (result).toFixed(2)
  }

  const getSmPercentage = (items) => {
    let sm = items.filter(pitcher => pitcher.pitching.trajectory == 'SM')

    let result = (sm / items.length) * 100

    return (result).toFixed(2)
  }

  const getFirstPitchAndStrike = (items) => {
    let resutl = 0

    items.forEach(pitcher => {
      if (pitcher.turn_pitches == 0 || pitcher.is_strike == true) {
        resutl++
      }
    });

    return resutl
  }

  const getFPSOPercentage = (items) => {
    let firstCounter = 0
    let secondCounter = getFirstPitchAndStrike(items)

    items.forEach(pitcher => {
      if (pitcher.bases == 0 || pitcher.bases == 4) {
        firstCounter++
      }
    });

    return ((firstCounter / secondCounter) * 100).toFixed(2)
  }

  const getFPSwPercentage = (items) => {
    let firstCounter = 0
    let secondCounter = getFirstPitchAndStrike(items)

    items.forEach(pitcher => {
      if (pitcher.turn_ball == 4 || pitcher.batting.trajectory == 'HBP') {
        firstCounter++
      }
    });

    return ((firstCounter / secondCounter) * 100).toFixed(2)
  }

  const getFPShPercentage = (items) => {
    let firstCounter = 0
    let secondCounter = getFirstPitchAndStrike(items)

    items.forEach(pitcher => {
      if (pitcher.bases >= 1 && pitcher.bases <= 4) {
        firstCounter++
      }
    });

    return ((firstCounter / secondCounter) * 100).toFixed(2)
  }

  return {
    getFirstPitchAndStrike,

    getTotalPitches,
    getBF,
    getBall,
    getStrike,
    getStrikePercentage,
    getFB,
    getFBPercentage,
    getCH,
    getCHPercentage,
    getCV,
    getCVPercentage,
    getSL,
    getSLPercentage,
    getOther,
    getOtherPercentage,
    getPBF,
    getMinorOrEqualThree,
    getFPSPercentage,
    getWeakPercentage,
    getKBFPercentage,
    getSmPercentage,
    getFPSOPercentage,
    getFPSwPercentage,
    getFPShPercentage
  }
}