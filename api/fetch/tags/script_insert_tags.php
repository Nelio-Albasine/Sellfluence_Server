<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require '../../conn/Wamp64Connection.php';
$conn = getWamp64Connection("Sellfluence_Public");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$sqlCreateTable = "CREATE TABLE IF NOT EXISTS Tags (
    Categoria VARCHAR(255),
    SubCategoria VARCHAR(255)
)";

if ($conn->query($sqlCreateTable) === TRUE) {
    echo "Tabela Tags criada com sucesso<br>";
} else {
    echo "Erro ao criar tabela: " . $conn->error;
}

$categorias = array(
    "Moda" => array(
        "Tendências", "looks", "dicas de estilo", "moda sustentável"
    ),
    "Beleza" => array(
        "Maquiagem", "cuidados com a pele", "cabelo", "produtos de beleza"
    ),
    "Fitness" => array(
        "Treinos", "alimentação saudável", "bem-estar", "esportes"
    ),
    "Viagens" => array(
        "Destinos", "dicas de viagem", "roteiros", "experiências"
    ),
    "Decoração" => array(
        "Ambientes", "estilos", "DIY", "organização"
    ),
    "Gastronomia" => array(
        "Receitas", "culinária", "restaurantes", "dicas de alimentação"
    ),
    "Humor" => array(
        "Memes", "vídeos engraçados", "piadas", "stand-up"
    ),
    "Sustentabilidade" => array(
        "Meio ambiente", "consumo consciente", "práticas ecológicas"
    ),
    "Música" => array(
        "Lançamentos", "artistas", "shows", "festivais"
    ),
    "Livros" => array(
        "Leitura", "resenhas", "gêneros literários", "autores"
    ),
    "Filmes e Séries" => array(
        "Críticas", "lançamentos", "gêneros", "plataformas de streaming"
    ),
    "Games" => array(
        "Jogos eletrônicos", "eSports", "dicas", "gameplay"
    ),
    "Fotografia" => array(
        "Técnicas", "dicas", "equipamentos", "edição"
    ),
    "Arte" => array(
        "Artes visuais", "pintura", "desenho", "escultura", "história da arte"
    ),
    "Esportes" => array(
        "Futebol", "basquete", "vôlei", "esportes olímpicos", "e-sports"
    ),
    "Tecnologia" => array(
        "Gadgets", "lançamentos", "tutoriais", "aplicativos"
    ),
    "Desenvolvimento Pessoal" => array(
        "Autoajuda", "produtividade", "organização", "mindfulness"
    ),
    "Maternidade" => array(
        "Gravidez", "parto", "dicas para pais"
    ),
    "Animais e Plantas" => array(
        "Pets", "cuidados", "curiosidades", "sustentabilidade"
    ),
    "Educação" => array(
        "Ensino", "aprendizagem", "dicas de estudo", "idiomas"
    ),
    "Empreendedorismo Digital" => array(
        "Negócios online", "marketing digital", "startups"
    ),
    "Finanças" => array(
        "Investimentos", "planejamento financeiro", "educação financeira"
    ),
    "Saúde" => array(
        "Prevenção", "doenças", "tratamentos", "bem-estar"
    ),
    "Direito" => array(
        "Leis", "direitos do consumidor", "justiça"
    ),
    "Design" => array(
        "Gráfico", "de produto", "de interiores", "arquitetura"
    ),
    "Espiritualidade" => array(
        "Religião", "crenças", "filosofia de vida"
    ),
    "Comida" => array(
        "Culinária regional", "vegana", "vegetariana", "receitas internacionais"
    ),
    "Literatura" => array(
        "Poesia", "contos", "romances", "gêneros literários"
    ),
    "Moda e Beleza" => array(
        "Plus size", "masculina", "infantil", "maquiagem profissional"
    ),
    "Música e Arte" => array(
        "Instrumentos musicais", "canto", "dança", "teatro"
    ),
    "Negócios e Carreira" => array(
        "Emprego", "empreendedorismo", "liderança", "marketing"
    ),
    "Idiomas" => array(
        "Inglês", "espanhol", "francês", "outros idiomas"
    ),
    "Software" => array(
        "Desenvolvimento de software", "programação", "aplicativos"
    ),
    "Tecnologia da Informação" => array(
        "Segurança digital", "redes sociais", "internet"
    )
);

try {
    $stmt = $conn->prepare("INSERT INTO Tags (Categoria, SubCategoria) VALUES (?, ?)");

    foreach ($categorias as $categoria => $subcategorias) {
        foreach ($subcategorias as $subcategoria) {
            $stmt->bind_param("ss", $categoria, $subcategoria);
            $stmt->execute();
        }
    }

    echo "Tags inseridas com sucesso!";
    
    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $e) {
    echo "Erro ao inserir registro: " . $e->getMessage();
}
?>
