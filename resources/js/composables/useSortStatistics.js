

const useSortStatistics = () => {

  function ordenarElementos(objeto, clave, orden) {
    const comparador = (a, b) => {
      if (clave.split('.').reduce((acc, part) => acc && acc[part], a) < clave.split('.').reduce((acc, part) => acc && acc[part], b)) {
        return orden === 'asc' ? -1 : 1;
      }
      if (clave.split('.').reduce((acc, part) => acc && acc[part], a) > clave.split('.').reduce((acc, part) => acc && acc[part], b)) {
        return orden === 'asc' ? 1 : -1;
      }
      return 0;
    };
    
    return objeto.sort(comparador);
  }


  return { ordenarElementos }
}

export default useSortStatistics