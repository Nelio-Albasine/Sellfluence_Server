const notificationId = "56519431";
const receiverId = "eo8b6sUgFOg8RtYumbc2DTi21lE3";
const isTo = 1;

const data = {
    notificationId: notificationId,
    receiverId: receiverId,
    isTo: isTo
};

const url = `https://sellfluence.com.br/api/notifications/UpdateOrDeleteNotifications.php`;


fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(data => {
    console.log('response:', data);
})
.catch((error) => {
    console.error('Error:', error);
});
