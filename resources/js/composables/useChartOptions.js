
const useChartOptions = () => {
  const colorsBars1 = ['#01F1E3','#8676FF','#00798C', '#F6A58A', '#E10600', '#FFB457']
  const colorsBars2 = ['#01F1E3','#8676FF','#00798C', '#F6A58A', '#FFB457']
  const categories1 = ['All', 'FB', 'CH', 'CV', 'SL', 'Other']
  const categories2 = ['FB', 'CH', 'CV', 'SL', 'Other']
  const categories3 = ['below 0','0-6', '6-15', '15-24', '24-40', '40-55', '55+']

  const donutChartOptions = {
    chart: {
      type: 'donut',
    },
    labels: ['Balls', 'Strikes'],
    colors: ['#8676FF', '#01F1E3'],
    legend: {
      show: false,
    },
    responsive: [{
      breakpoint: 480,
      options: {
        chart: {
          width: 200
        },
      }
    }]
  }

  const radiaChartOptions = {
    chart: {
      type: 'radialBar'
    },
    labels: ['Right', 'Middle', 'Left'],
    legend: {
      show: true,
      position: 'bottom'
    },
    plotOptions: {
      radialBar: {
        dataLabels: {
          total: {
            show: true,
            label: 'Practice',
            formatter: function (w) {
              let final = w.globals.seriesTotals.reduce((a, b) => {
                return a + b
              }, 0) / w.globals.series.length

              return final.toFixed(0) + '%'
            }
          },
          value: {
            show: true,
            fontSize: '14px',
          },
        }
      }
    }
  }

  const barChartOptions = (maxYaxis = 350, colorsOp = 1, categoriesOp = 1) => {
    return {
      chart: {
        height: 350,
        type: 'bar',
        toolbar: {
          show: false
        }
      },
      colors: colorsOp == 1 ? colorsBars1 : colorsBars2,
      plotOptions: {
        bar: {
          columnWidth: '50%',
          distributed: true,
        }
      },
      dataLabels: {
        enabled: false
      },
      legend: {
        show: false
      },
      xaxis: {
        categories: categoriesOp == 1 ? categories1 :
                    categoriesOp == 2 ? categories2 : categories3,
        labels: {
          rotate: -45,
          style: {
            colors: colorsOp == 1 ? colorsBars1 : colorsBars2,
            fontSize: '12px'
          }
        }
      }
    }
  }

  const dinamicChartoptions = (dinamicCategories, monthsShow) => {
    return {
      chart: {
        height: 350,
        type: 'line',
        // toolbar: {
        //   show: false
        // }
      },
      tooltip:{
        z:{
          'title':"Weight Ball"
        }
      },
      dataLabels: {
        enabled: true,
      },
      xaxis: {
        type: 'datetime',
      },
    }
  }

  const dinamicChartOptionsPlayers = (titleYChart, dinamicCategories) => {

    return {
      chart: {
        height: 350,
        type: 'line',
        zoom: {
          enabled: false
        },
        toolbar: {
          show: false,
        }
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        // curve: 'smooth'
        curve: 'straight'
      },
      colors: ['#082247'],
      grid: {
        row: {
          colors: ['#E7EAEE', 'transparent'],
          opacity: 0.3
        },
      },
      markers: {
        colors: ['#A0A0A0']
      },
      yaxis: {
        min: 0,
        tickAmount: 8,
        // max: 100, //maxYaxis.value,
        labels: {
          style: {
            fontSize:  '14px',
            fontWeight:  'bold',
            colors:  ['#082247']
          },
        },
        title: {
          text: titleYChart,
          style: {
            fontSize:  '20px',
            fontWeight:  'bold',
            color:  '#E10600'
          },
        },
        dataLabels: {
          style: {
            colors: ['#E10600']
          }
        },
      },
      xaxis: {
        type: 'datetime',
      },
      tooltip:{
        z:{
          'title':"Weight Ball"
        }
      },
    }
  }

  const dinamicChartOptionsFitness = (titleYChart, categories) => {
    return {
      chart: {
        height: 350,
        type: 'area',
        zoom: {
          enabled: false
        },
        toolbar: {
          show: false,
        }
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        curve: 'smooth'
        // curve: 'straight'
      },
      colors: ['#082247'],
      grid: {
        row: {
          colors: ['#E7EAEE', 'transparent'],
          opacity: 0.3
        },
      },
      markers: {
        colors: ['#A0A0A0']
      },
      yaxis: {
        min: 0,
        tickAmount: 8,
        // max: 100, //maxYaxis.value,
        labels: {
          style: {
            fontSize:  '14px',
            fontWeight:  'bold',
            colors:  ['#082247']
          },
        },
        title: {
          text: titleYChart,
          style: {
            fontSize:  '20px',
            fontWeight:  'bold',
            color:  '#E10600'
          },
        },
        dataLabels: {
          style: {
            colors: ['#E10600']
          }
        },
      },
      xaxis: {
        type: 'category',
        // categories: getLastMonths(dinamicCategories == 0 ? 12 : dinamicCategories, series),
        categories: categories,
        labels: {
          style: {
            fontSize:  '14px',
            fontWeight:  'bold',
            colors:  ['#082247']
          },
        },
        title: {
          text: "Time",
          style: {
            fontSize:  '20px',
            fontWeight:  'bold',
            color:  '#E10600'
          },
        },
        dataLabels: {
          style: {
            colors: ['#E10600']
          }
        },
      },
    }
  }

  const getLastMonths = (numberMonth = 12) => {
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const currentDate = new Date();
    const lastMonths = [];

    for (let i = 0; i <= numberMonth; i++) {
      const currentMonth = currentDate.getMonth() - 1;
      const currentYear = currentDate.getFullYear();

      lastMonths.push(`${currentYear} - ${monthNames[currentMonth + 1]}`);

      // Restar un mes a la fecha actual
      currentDate.setMonth(currentDate.getMonth() - 1);
    }

    let reverseMonths = lastMonths
    reverseMonths.reverse()
    return reverseMonths;
  }

  return {
    donutChartOptions,
    radiaChartOptions,
    barChartOptions,
    dinamicChartoptions,
    dinamicChartOptionsPlayers,
    dinamicChartOptionsFitness
  }
}

export default useChartOptions
