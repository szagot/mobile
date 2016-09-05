<?php
/**
 * Gerador de planilha CSV para Mobly
 * User: Daniel Bispo
 * Date: 23/08/2016
 * Time: 15:05
 */
if (! isset($_SESSION[ 'TMWXD' ])) {
    @session_start();
}

date_default_timezone_set('America/Sao_Paulo');

if ((time() - $_SESSION[ 'TMWXD' ][ 'acesso' ]) > $_SESSION[ 'TMWXD' ][ 'periodo' ]) {
    // Deslogar ao final do período
    die('Acesso negado.');
}

// Pega dados iniciais do serviço, se houver
$pathConf = __DIR__ . DIRECTORY_SEPARATOR . 'mobly.conf';
$config = null;
if (file_exists($pathConf)) {
    $config = json_decode(file_get_contents($pathConf));
    // Verificando existencia de chave obrigatória
    if (! isset($config->descType)) {
        $config = null;
    }
}
$temConfig = ! is_null($config);

// É apenas pra retornar o status do serviço? (mobly/gen.php?service=status)
$serviceStatus = filter_input(INPUT_GET, 'service');
if ($serviceStatus == 'status') {
    if ($temConfig) {
        die((isset($config->moblyAtivo) && $config->moblyAtivo == 1) ? '1' : '0');
    }

    // Se chegou aqui é pq o status não está ativo
    die('0');
}

require_once '../config/conecta.class.php';

// Inicializa variáveis
$descType = filter_input(INPUT_POST, 'desc_type');
$moblyAtivo = filter_input(INPUT_POST, 'mobly_ativo');
$erro = filter_input(INPUT_GET, 'msg');
$pdo = new Conecta();

// ID Parceiro não informado?
if (! $descType || empty($descType)) {
    header('Content-type: text/html; charset=utf-8');

    // Verificando arquivo de pesquisa
    if ($temConfig) {
        $descType = $config->descType;
        $moblyAtivo = (isset($config->moblyAtivo) && $config->moblyAtivo == 1) ? 1 : 0;
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
    'descType'   => $descType,
    'moblyAtivo' => $moblyAtivo
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
        PRO_NOME AS `Name`,
        FOR_NOME AS `Brand`,
        PRO_REF AS `Model`,
        PRO_GTIN AS `ProductId`,
        PRO_REF AS `SellerSKU`,
        PRO_REF AS `ParentSKU`,
        PRO_ESTOQUE AS `Quantity`,
        PRO_ALTURA AS `BoxHeight`,
        PRO_LARGURA AS `BoxWidth`,
        PRO_COMPRIMENTO AS `BoxLength`,
        ROUND(PRO_PESO / 1000, 2) AS `BoxWeight`,
        PRO_DIAS_PRAZO AS `SupplierDeliveryTime`,
        PRO_ALTURA AS `ProductHeight`,
        PRO_LARGURA AS `ProductWidth`,
        PRO_COMPRIMENTO AS `ProductLength`,
        ROUND(PRO_PESO / 1000, 2) AS `ProductWeight`,
        CONCAT(PRO_ALTURA,'x',PRO_LARGURA,'x',PRO_COMPRIMENTO,'cm') AS `SizeDescription`,
        MOB_COLOR AS `Color`,
        MOB_COLOR_FAMILY AS `ColorFamily`,
        MOB_MATERIAL AS `Material`,
        MOB_MATERIAL_FAMILY AS `MaterialFamily`,
        ROUND(PRO_VALOR,2) AS `Price`,
        IF( $ePromo, ROUND(PRO_PROMOCAO,2), '') AS `SalePrice`,
        PRO_PROMO_INI AS `SaleStartDate`,
        PRO_PROMO_FIM AS `SaleEndDate`,
        '' AS `PrimaryCategory`,
        '' AS `Categories`,
        '' AS `AnchorCategory`,
        '' AS `RepProductCategory`,
        '' AS `RepCategoryOtb`,
        '' AS `RepProductDepartment`,
        '' AS `Manager`,
        '' AS `GroupCategoryOtb`,
        '' AS `RepProductSubcategory`,
        MOB_PRODUCT_WARRANTY AS `ProductWarranty`,
        MOB_COLOR AS `ColorSimple`,
        MOB_COLOR_FAMILY AS `ColorSupplier`,
        PRO_NOME AS `SupplierSimpleName`,
        'Único' AS `Variation`,
        IF(PRO_SOB_ENCOMENDA=1, 'Importado', 'Nacional') AS `OriginCountry`,
        (SELECT CON_UF_LOJA FROM config LIMIT 0,1) AS `OriginState`,
        $getDesc AS `Description`,
        '' AS `ProductContents`,
        '' AS `ProductInstructions`,
        '' AS `MainImage`,
        '' AS `Image2`,
        '' AS `Image3`,
        '' AS `Image4`,
        '' AS `Image5`,
        MOB_WEIGHT_CAPACITY AS `WeightCapacity`,
        MOB_DIMENSIONS AS `Dimensions`,
        MOB_WATT AS `Watt`,
        MOB_INDICATE_AGE AS `IndicatedAge`,
        MOB_FOR_MATTRESS_TYPE AS `ForMattressType`,
        MOB_NUMBER_OF_PIECES AS `NumberOfPieces`,
        MOB_SEAT_GROUND_HEIGHT AS `SeatGroundHeight`,
        MOB_RECOMMENDED_WEIGHT_CH AS `RecommendedWeightCh`,
        MOB_PAINTING_FINISHING AS `PaintingFinishing`,
        MOB_COATING AS `Coating`,
        MOB_TYPE AS `Type`,
        MOB_MATERIAL_TABLE_TOP AS `MaterialTableTop`,
        MOB_FORMAT_GI AS `FormatGl`,
        MOB_DECORATIVE AS `Decorative`,
        MOB_TYPE_OF_FOOT_GI AS `TypeOfFootGl`,
        MOB_NUMBER_OF_DOORS AS `NumberOfDoors`,
        MOB_NUMBER_OF_DRAWERS AS `NumberOfDrawers`,
        MOB_NUMBER_OF_SHELVES AS `NumberOfShelves`,
        MOB_LAMP_NUMBER AS `LampNumber`,
        MOB_GLOBAL_CAPACITY AS `GlobalCapacity`,
        MOB_SOCKET_TYPE AS `SocketType`,
        MOB_VOLTAGE_HW AS `VoltageHw`,
        MOB_ASSEMBLY_REQUIRED AS `AssemblyRequired`,
        
        produto.PRO_ID AS TEMP_ID

    FROM produto
        INNER JOIN mobly ON mobly.PRO_ID = produto.PRO_ID
        LEFT JOIN fornecedor ON produto.FOR_ID = fornecedor.FOR_ID
    
    WHERE
      SUB_PRO_ID IS NULL AND PRO_VALOR > 0 AND 
      ( PRO_VISIBILIDADE NOT LIKE '%mobly%' OR PRO_VISIBILIDADE IS NULL ) AND
      MOB_COLOR IS NOT NULL AND MOB_COLOR <> '' AND
      MOB_COLOR_FAMILY IS NOT NULL AND MOB_COLOR_FAMILY <> '' AND
      MOB_MATERIAL IS NOT NULL AND MOB_MATERIAL <> '' AND
      MOB_MATERIAL_FAMILY IS NOT NULL AND MOB_MATERIAL_FAMILY <> '' AND
      PRO_ALTURA > 0 AND PRO_LARGURA > 0 AND PRO_COMPRIMENTO > 0 AND PRO_PESO > 0
");

// Tratando os dados
$saida = '';
foreach ($produtos as $produto) {

    // Pegando Imagens
    $imagens = $pdo->execute("
      SELECT 
        CONCAT((SELECT CON_HEADER_URL FROM config LIMIT 0,1),'img/produtos/',IMG_NOME) AS img 
      
      FROM img_prod 
      
      WHERE PRO_ID = '{$produto->TEMP_ID}' AND IMG_TIPO = 1 ORDER BY IMG_ORDEM
    ");

    // Tem pelo menos 1 imagem?
    if (! isset($imagens[ 0 ]->img)) {
        continue;
    }

    // Salvando imagens
    $produto->MainImage = $imagens[ 0 ]->img;
    $produto->Image2 = isset($imagens[ 1 ]->img) ? $imagens[ 1 ]->img : '';
    $produto->Image3 = isset($imagens[ 2 ]->img) ? $imagens[ 2 ]->img : '';
    $produto->Image4 = isset($imagens[ 3 ]->img) ? $imagens[ 3 ]->img : '';
    $produto->Image5 = isset($imagens[ 4 ]->img) ? $imagens[ 4 ]->img : '';

    // Apagando chaves temporárias
    unset($produto->TEMP_ID);

    // Corrigindo descrição
    $produto->Description =
        // Remove ;
        trim(str_replace(';', ',',
            // Remove espaços extras
            preg_replace('/[\n\r\s\t]+/i', ' ',
                // Limpa tags HTML
                strip_tags($produto->Description, '<p><strong>')
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
$saida = utf8_decode( substr($saida, 0, -1) );

if (! empty($saida)) {
    // Cabeçalhos
    header('Content-type: text/csv; charset=iso-8859-1');
    header('Content-Disposition: attachment; filename=mobly-' . date('Y-m-d-H-i') . '.csv');
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