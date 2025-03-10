const postData = {
  selectedAges: [],
  selectedCities: [
    "Morrumbene"
  ],
  selectedContentTypes: [
    "reels",
    "stories"
  ],
  selectedNiches: [
  ],
  selectedPrices: {
    max: 10.0,
    min: 50.0
  },
  selectedStates: [ // aplicável para o Brasil
    "Inhambane"
  ],
  cursor: null
};

fetch('http://localhost/Selfluence/api/fetch/influencers/filtros.php', {  // Removido .php duplicado
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(postData)
})
  .then(response => {
    if (!response.ok) {
      throw new Error(`Erro HTTP! status: ${response.status}`);
    }
    return response.text(); // Alterado para .json() para parse automático
  })
  .then(data => {
    console.log('Resposta:', data);
  })
  .catch(error => {
    console.error('Erro na requisição:', error);
  });