import fetch from 'node-fetch';

// Função para obter o código de um estado específico
async function getEstadoCodigo(estado) {
  const response = await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados');
  const estados = await response.json();
  const estadoSelecionado = estados.find(e => e.nome === estado || e.sigla === estado);

  if (!estadoSelecionado) {
    throw new Error('Estado não encontrado');
  }

  return estadoSelecionado.id;
}

// Função para obter as cidades de um estado
async function getCidades(estado) {
  const estadoCodigo = await getEstadoCodigo(estado);
  const response = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${estadoCodigo}/municipios`);
  const cidades = await response.json();
  return cidades;
}

// Exemplo de uso
getCidades('SP').then(cidades => {
  console.log(cidades);
}).catch(error => {
  console.error('Erro:', error);
});
