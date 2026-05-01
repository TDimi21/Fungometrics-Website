
export const donutChartOptions = {
  chart: {
    type: 'donut',    
  },
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

export const radiaChartOptions = {
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
        }
      }
    }
  }
}

export const barChartOptions = {
  chart: {
    height: 350,
    type: 'bar',
    toolbar: {
      show: false
    }
  },
  colors: ['#01F1E3','#8676FF','#00798C', '#F6A58A', '#E10600', '#FFB457'],
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
    categories: ['All', 'FB', 'CH', 'CV', 'SL', 'Other'],
    labels: {
      rotate: -45,
      style: {
        colors: ['#01F1E3','#8676FF','#00798C', '#F6A58A', '#E10600', '#FFB457'],
        fontSize: '12px'
      }
    }
  },
  yaxis: {
    min: 0,
    max: pitchThrows.totals,
    tickAmount: 8,
  }
}
