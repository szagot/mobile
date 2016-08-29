<?php
/**
 * Gerador de planilha CSV para Mobly
 * User: Daniel Bispo
 * Date: 23/08/2016
 * Time: 15:05
 */

require_once '../config/conecta.class.php';

// Inicializa variáveis
$idParceiro = filter_input(INPUT_POST, 'id_parceiro');
$descType = filter_input(INPUT_POST, 'desc_type');
$erro = filter_input(INPUT_GET, 'msg');
$pathConf = __DIR__ . DIRECTORY_SEPARATOR . 'b2w.conf';
$pdo = new Conecta();

// ID Parceiro não informado?
if (! $idParceiro || empty($idParceiro)) {
    header('Content-type: text/html; charset=utf-8');

    // Verificando arquivo de pesquisa
    if (file_exists($pathConf)) {
        $config = json_decode(file_get_contents($pathConf));
        // Verificando existencia de chaves
        if (isset($config->idParceiro, $config->descType)) {
            $idParceiro = $config->idParceiro;
            $descType = $config->descType;
        }
    }

    // Pegando Descrições disponíveis
    $descricoes = $pdo->execute('SELECT DES_NOME FROM descricao_prod GROUP BY DES_NOME ORDER BY DES_NOME');
    $descTypes = [];
    if ($descricoes) {
        foreach ($descricoes as $desc) {
            $descTypes[] = $desc->DES_NOME;
        }
    }

    // Montando form
    require_once 'nav/form.php';
    exit;
}

// Gravando Valores escolhidos por padrão
file_put_contents($pathConf, json_encode([
    'idParceiro' => $idParceiro,
    'descType'   => $descType
]));

// Diferentes descrições
$desCurta = 'IF( PRO_DESC_CURTA IS NULL, PRO_NOME, PRO_DESC_CURTA )';
$desDefault = "IF( PRO_DESCRICAO IS NULL, $desCurta, PRO_DESCRICAO )";
$desPadrao = "(SELECT DES_CONT FROM descricao_prod WHERE descricao_prod.PRO_ID = produto.PRO_ID AND DES_NOME = '$descType' ORDER BY DES_ORDEM LIMIT 0,1)";

// Qual deve ser?
if ($descType == 'curta') {
    $getDesc = $desCurta;
} elseif ($descType == 'default') {
    $getDesc = $desDefault;
} else {
    $getDesc = "IF( $desPadrao IS NULL, $desDefault, $desPadrao )";
}

// Verifica Promo
$ePromo = 'PRO_PROMOCAO > 0 AND 
          ( DATE(PRO_PROMO_INI) <= DATE(NOW()) OR PRO_PROMO_INI = \'0000-00-00\' OR PRO_PROMO_INI IS NULL ) AND
          ( DATE(PRO_PROMO_FIM) >= DATE(NOW()) OR PRO_PROMO_FIM = \'0000-00-00\' OR PRO_PROMO_FIM IS NULL )';

// Gerando pesquisa
$produtos = $pdo->execute("
    SELECT 
        '$idParceiro' AS ID_PARCEIRO,
        PRO_REF AS ID_ITEM_PARCEIRO,
        PRO_NOME AS NOME_ITEM,
        ROUND(PRO_PESO,0) AS PESO_UNITARIO,
        $getDesc AS DESCRICAO_ITEM,
        '' AS IMAGEM_ITEM,
        ROUND(PRO_ALTURA * 1000, 0) AS ALTURA,
        ROUND(PRO_LARGURA * 1000, 0) AS LARGURA,
        ROUND(PRO_COMPRIMENTO * 1000, 0) AS COMPRIMENTO,
        PRO_GTIN AS EAN,
        '' AS ID_ITEM_PAI,
        '' AS NOME_ITEM_PAI,
        'E' AS TIPO_ITEM,
        IF( PRO_ATIVO = 1 AND PRO_ESTOQUE > 0 AND PRO_VISIBILIDADE NOT LIKE '%loja%', 'A', 'I' ) AS SITUACAO_ITEM,
        PRO_DIAS_PRAZO AS PRAZO_XD,
        IF( $ePromo, ROUND(PRO_VALOR,2), '' ) AS PRECO_DE,
        ROUND(IF( $ePromo, PRO_PROMOCAO, PRO_VALOR ),2) AS PRECO_POR,
        PRO_ESTOQUE AS QTDE_ESTOQUE,
        '' AS DEPARTAMENTO,
        '' AS SETOR,
        '' AS FAMILIA,
        '' AS SUB_FAMILIA,
        IF( PRO_SOB_ENCOMENDA = 1, '1', '0' ) AS PROCEDENCIA_ITEM,
        FOR_NOME AS MARCA,
        produto.PRO_ID AS TEMP_ID,
        SEC_URL AS TEMP_SEC

    FROM produto
        LEFT JOIN fornecedor ON produto.FOR_ID = fornecedor.FOR_ID
        INNER JOIN secao_prod ON produto.SEC_ID = secao_prod.SEC_ID
    
    WHERE
      SUB_PRO_ID IS NULL AND PRO_VALOR > 0
");

// Tratando os dados
$saida = '';
foreach ($produtos as $produto) {
    // Verificando seções
    $produto->TEMP_SEC = str_replace(' ', '', $produto->TEMP_SEC);
    if (! preg_match('/^[0-9]+\|[a-z]+/i', $produto->TEMP_SEC)) {
        continue;
    }

    // Pegando Imagens
    $imagens = $pdo->execute("
      SELECT 
        CONCAT((SELECT CON_HEADER_URL FROM config LIMIT 0,1),'img/produtos/',IMG_NOME) AS img 
      
      FROM img_prod 
      
      WHERE PRO_ID = '{$produto->TEMP_ID}' AND IMG_TIPO = 1 ORDER BY IMG_ORDEM
    ");

    if (! isset($imagens[ 0 ]->img)) {
        continue;
    }

    // Salvando imagens
    $imgTemp = [];
    foreach ($imagens as $img) {
        $imgTemp[] = $img->img;
    }

    $produto->IMAGEM_ITEM = implode(',', $imgTemp);

    // Formatando Seçoes
    list($depto, $setor, $familia, $subFamilia) = explode('>', $produto->TEMP_SEC);
    list($produto->DEPARTAMENTO) = explode('|', $depto);
    list($produto->SETOR) = explode('|', $setor);
    list($produto->FAMILIA) = explode('|', $familia);
    list($produto->SUB_FAMILIA) = explode('|', $subFamilia);

    // Apagando chaves temporárias
    unset($produto->TEMP_ID, $produto->TEMP_SEC);

    // Corrigindo descrição
    $produto->DESCRICAO_ITEM =
        // Remove ;
        trim(str_replace(';', ',',
            // Remove espaços extras
            preg_replace('/[\n\r\s\t]+/i', ' ',
                // Limpa tags HTML
                strip_tags($produto->DESCRICAO_ITEM)
            )
        ));

    // Montando Saída
    // Titulos
    if (empty($saida)) {
        foreach ($produto as $chave => $valor) {
            $saida .= $chave . ';';
        }

        $saida = substr($saida, 0, -1) . PHP_EOL;
    }

    // Valores
    foreach ($produto as $valor) {
        $saida .= $valor . ';';
    }

    $saida = substr($saida, 0, -1) . PHP_EOL;

}

// Remove quebra de linha final
$saida = substr($saida, 0, -1);

if (! empty($saida)) {
    // Cabeçalhos
    header('Content-type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=b2w-' . date('Y-m-d-H-i') . '.csv');
    header('Cache-Control: no-cache, no-store, must-revalidate'); # HTTP 1.1
    header('Pragma: no-cache'); # HTTP 1.0
    header('Expires: 0'); # Proxies

    die($saida);
}

// Se não houveram dados a serem mostrados, retorna ao inicio.
if (! headers_sent()) {
    header('Location: ./gen.php#empty');
    exit;
}

echo 'Nenhum registro encontrado';