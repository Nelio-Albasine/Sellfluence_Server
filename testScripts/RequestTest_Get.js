async function sendNotification() {
    const url = 'http://localhost/Selfluence/api/fetch/UserInfo/UserRegistrationStatus.php';
    const headers = {
        'Content-Type': 'application/json'
    };
    
    const userId = "eo8b6sUgFOg8RtYumbc2DTi21lE3";
    const requestUrl = `${url}?userId=${encodeURIComponent(userId)}`;

    try {
        const response = await fetch(requestUrl, {
            method: 'GET',
            headers: headers
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
        }

        const data = await response.json();
        console.log('Success:', data);
    } catch (error) {
        console.error('Error:', error);
    }
}

sendNotification();
