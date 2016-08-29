<?php
if (! isset($descTypes)) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Gerador de Planilha da Mobly</title>
        <link href="nav/estilo.css" rel="stylesheet" type="text/css">
    </head>
    <body>
        <form id="formB2W" action="" method="post">
            <div class="campo">
                <label class="title" for="desc_type">Descrição</label>
                <select class="desc" name="desc_type" id="desc_type">
                    <option value="curta" <?= ($descType == 'curta') ? 'selected' : '' ?>>Descrição Curta</option>
                    <option value="default" <?= ($descType == 'default' || empty($descType)) ? 'selected' : '' ?>>
                        Descrição Padrão
                    </option>
                    <?php
                    foreach ($descTypes as $desc) {
                        ?>
                        <option <?= ($descType == $desc) ? 'selected' : '' ?>><?= $desc ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="campo">
                <label class="title"></label>
                <button class="btn" type="submit">Gerar</button>
            </div>
        </form>

        <div id="empty" class="modal-box">
            <div class="modal-content">
                <p>
                    <b>Nenhum registro encontrado.</b>
                </p>
                <p>
                    Verifique se os produtos possuem imagem e se todas as seções têm
                    as categorias da Mobly.
                </p>
                <a href="#close" class="close-link">X</a>
            </div>
        </div>
    </body>
</html>