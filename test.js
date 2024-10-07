async function sendNotification() {
    const url = 'https://sellfluence.com.br/api/notifications/SaveNotificationIntoDB.php';
    const headers = {
        'Content-Type': 'application/json'
    };
    const requestBody = {
        userName: "Maycon Castro",
        userEmail: "nelioalbasine#gmail.com"
    };

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(requestBody)
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
        }

        const data = await response.text();
        console.log('Success:', data);
    } catch (error) {
        console.error('Error:', error);
    }
}

sendNotification();
