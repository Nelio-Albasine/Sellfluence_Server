// Função para enviar uma requisição POST
async function updateUnreadMessages() {
    const url = 'http://localhost/Selfluence/fetch/UpdateUnreadMessages.php';  // Coloque aqui o URL correto para o seu endpoint PHP

    const data = {
        chatId: 208155,  // ID do chat fornecido
        messagesIds: ["wuO6oT"]  // Conjunto de messageIds (strings)
    };

    try {
        const response = await fetch(url, {
            method: 'POST',  // Método POST
            headers: {
                'Content-Type': 'application/json',  // Tipo de conteúdo JSON
            },
            body: JSON.stringify(data),  // Envia o corpo da requisição em formato JSON
        });

        if (!response.ok) {
            throw new Error(`Erro na requisição: ${response.status}`);
        }

        const result = await response.json();  // Obtém a resposta como JSON
        console.log('Resposta do servidor:', result);  // Exibe a resposta no console

    } catch (error) {
        console.error('Erro na atualização das mensagens não lidas:', error);
    }
}

// Chama a função para atualizar as mensagens
updateUnreadMessages();
