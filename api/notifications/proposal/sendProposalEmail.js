//maycond90@gmail.com
let discussReason = "O influenciador gostaria de negociar o valor e discutir o cronograma da campanha.";
let refuseReason = "Infelizmente, o orçamento não atende às minhas expectativas no momento.";
let token = "darRauAWRDiztjbENuQ_63:APA91bH5k9GMD-GOF60qRjfmQ1P2n36mpNUPOwrQlkT-0l-Y-qhnF-C9kbxzW-akos3xjR6yEdzw4KUMneFlp9NBkJ9GD9tAuqlKvNIhWzyKIhh3Tl2vEeyqVQfUIhvZhXjzCY3OsE_a"
token = "cdaaTK-mQ6OAriLYbsJ_jP:APA91bHE-zRFUaugtO3okLJweBYFWUzewvvOq1Hb83ZGNoJhF9fFvMtl1WJEZ0R6C2vHMVH13SfKidiiQ10zXhvdZEOF4Ca5U8OEFVeH3Hz6neInzozwz6-ZQY4ud7W8Ts5RZg5W9opK"
let senderId = null

const data = {
  to: "nelioalbasine@gmail.com",
  senderId: senderId,
  companyNotificationToken: token,  // Email do destinatário
  proposalResponse: JSON.stringify({
    proposalResponseStatus: 0, // Status da resposta da proposta (0 = aceita, 1 = discutir, 2 = negada)
    Reason: refuseReason      // Motivo para recusa (necessário apenas se o status for 2)
  }),
  chatId: "814395",            // ID do chat
  messageId: "WvZC2w",          // ID da mensagem
  proposalTotalPrice: "R$ 4.345,67",
  proposalApprovaralDate: new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }),  // Preço total da proposta
  participantsInfo: JSON.stringify({
    companyName: "Nelio Albasine",          // Nome da empresa
    influencerName: "Florinda Flores", // Nome do influenciador
    companyProfilePic: "https://i.scdn.co/image/ab6761610000e5ebae07171f989fb39736674113",  // URL da foto de perfil da empresa
    influencerProfilePic: "https://pbs.twimg.com/media/DzhmtFBWsAACBSN.jpg"  // URL da foto de perfil do influenciador
  })
};


async function sendProposal() {
  try {
    const response = await fetch('https://sellfluence.com.br/api/notifications/proposal/SendProposalResponse.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data)
    });


    if (response.ok) {
      const result = await response.json();
      console.log("Resposta do servidor:", result);
    } else {
      console.error("Erro na requisição:", response.status);
    }
  } catch (error) {
    console.error("Erro ao enviar a requisição:", error);
  }
}

// Chama a função para enviar a requisição
sendProposal();
