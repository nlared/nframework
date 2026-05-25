<?
require 'common.php';
$nframework->usecommon = true;
$dialog = new Dialog(['title' => 'title']);
echo $dialog;
?>
<button id="agregar">Abrir</button>
<script>
    $('#agregar').click(function() {
        <?= $dialog->id ?>.showModal();
    });
</script>