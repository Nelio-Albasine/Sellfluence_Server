async function mYTestFunction() {
    const url = `https://sellfluence.com.br/api/notifications/send_proposta_notification.php`;

    let influencerToken = "deT4-n46QYOz8IXpkbanWS:APA91bF4XCR1csIv6PJQOMWf3ITtw8DsxtGtqFiZUeJCLiH-s0PZIdBVuzgnqRND7akIYaAlVOU2Ho-IzW7sjkHQ693plOtxKQ1e2RaJCopY9c8-oC6jS6AYmsJDIhAP15SDQJa5Eioz";
    let senderId = "eo8b6sUgFOg8RtYumbc2DTi21lE3";
    let receiverId = "eo8b6sUgFOg8RtYumbc2DTi21lE3";
    let receiverName = "Nelio Programador";
    let receiverEmail = "nelioalbasine@gmail.com";

    let data = {
        to: influencerToken,
        senderId: senderId,
        receiverId: receiverId,
        amount: 7070.02, // Ajuste o valor conforme necessário
        proposalItems: {
            "1": "1x Camisa do time do seu sonho",
            "2": "1x Camisa do time do seu sonho",
        }, // Array vazio
        receiverName: receiverName,
        receiverEmail: receiverEmail,
        companyName: "Empresa Inc",
        companyProfilePicUrl: "https://sellfluence.com.br/0/Imagens/food_img.jpg"
    };

    try {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            throw new Error('A resposta da rede não foi ok');
        }

        const paramData = await response.json();
        console.log(`Row Response`, paramData);

    } catch (error) {
        console.error('Houve um problema com a operação fetch:', error);
    }
}

mYTestFunction();
