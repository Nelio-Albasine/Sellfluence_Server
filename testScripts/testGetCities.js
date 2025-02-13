const estados = "RJ";
const url = `http://localhost/Selfluence/api/fetch/cities/query_cities.php?estados=${estados}`;

fetch(url, {
    method: "GET",
    headers: {
        "Content-Type": "application/json"
    }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error("Erro:", error));
