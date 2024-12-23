let nextCursor = null;
const limit = 10; 

async function getInfluencers(cursor = null) {
    try {
        const url = new URL('http://localhost/Selfluence/api/fetch/Influencers/GetHomeInfluencers.php');
        url.searchParams.append('limit', limit);
        if (cursor) {
            url.searchParams.append('cursor', cursor);
        }

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`Erro na requisição: ${response.status}`);
        }

        const responseBody = await response.json();

        if (responseBody && responseBody.data) {
            console.log('Dados dos influenciadores:', responseBody.data);
            console.log('Resposta do nextCursor:', responseBody.nextCursor);
            nextCursor = responseBody.nextCursor || null;

            // Verifica se há mais dados a serem buscados
            if (nextCursor) {
                console.log(`Pronto para buscar mais dados com cursor: ${nextCursor}`);
            } else {
                console.log('Não há mais influenciadores para carregar.');
            }
        } else {
            console.error('Resposta inválida ou vazia.');
        }
    } catch (error) {
        console.error('Erro ao obter influenciadores:', error);
    }
}

getInfluencers("123456789_1")

