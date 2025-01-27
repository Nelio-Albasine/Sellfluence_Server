# Ao enviar a notificacao, colocar para enviar:


 //data to be used on the notification pop-up
        val senderName = data["senderName"] ?: ""
        val senderProfilePicURL = data["senderProfilePicURL"] ?: ""
        val chatId = data["chatId"]?.toInt() ?: 0
        val message = data["message"] ?: ""


//data to be used on the chatsList viewModel to update the screen
        chatId = messageData["chatId"]?.toInt() ?: 0,
        otherParticipant = messageData["otherParticipant"] ?: "", //who sent the message
        whoStarted = null,
        startedAt = null,
        updatedAt = messageData["updatedAt"] ?: ""       